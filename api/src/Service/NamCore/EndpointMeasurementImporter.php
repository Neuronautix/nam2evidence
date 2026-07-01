<?php

declare(strict_types=1);

namespace App\Service\NamCore;

use App\Entity\NamCore\Assay;
use App\Entity\NamCore\BiologicalSystem;
use App\Entity\NamCore\Donor;
use App\Entity\NamCore\EndpointMeasurement;
use App\Entity\NamCore\Exposure;
use App\Entity\NamCore\RawDataFile;
use App\Entity\NamCore\Sample;
use App\Entity\NAMStudy;
use App\Entity\Project;
use Doctrine\ORM\EntityManagerInterface;

/**
 * CSV → canonical NAM-CORE EndpointMeasurement importer.
 *
 * Two modes:
 *   preview()  — parse headers + sample rows, auto-suggest a column mapping.
 *   import()   — apply a (possibly human-edited) mapping, validate required
 *                fields, normalize units, resolve business-key references to
 *                existing NAM-CORE entities, persist rows, and return a summary.
 *
 * Import is deliberately forgiving: unresolved references and unknown units do
 * not abort the run — they are recorded as warnings so the "before" demo state
 * can surface real, navigable standardization gaps rather than a hard failure.
 */
final class EndpointMeasurementImporter
{
    /** Canonical NAM-CORE endpoint-measurement fields available as mapping targets. */
    public const TARGET_FIELDS = [
        'study_id', 'assay_id', 'sample_id', 'biological_system_id', 'exposure_id',
        'endpoint_id', 'endpoint_label', 'endpoint_ontology_iri',
        'value', 'unit', 'unit_ontology_iri',
        'timepoint_value', 'timepoint_unit',
        'replicate_id', 'batch_id', 'donor_id', 'device_id',
        'raw_file_id', 'analysis_activity_id',
        'qc_status', 'exclusion_status', 'exclusion_reason',
    ];

    /** Fields required for a valid canonical measurement (import-time minimum). */
    private const REQUIRED = ['endpoint_id', 'value', 'unit'];

    /** @var array<string, string[]> canonical field => header aliases (lowercased) */
    private const ALIASES = [
        'study_id'             => ['study', 'study_id', 'studyid'],
        'assay_id'             => ['assay', 'assay_id', 'assayid'],
        'sample_id'            => ['sample', 'sample_id', 'sampleid', 'sample_code'],
        'biological_system_id' => ['biological_system', 'bio_system', 'system', 'model_system'],
        'exposure_id'          => ['exposure', 'exposure_id', 'treatment', 'test_article'],
        'endpoint_id'          => ['endpoint', 'endpoint_id', 'endpointid', 'assay_endpoint', 'readout'],
        'endpoint_label'       => ['endpoint_label', 'endpoint_name', 'measurement'],
        'endpoint_ontology_iri'=> ['endpoint_iri', 'endpoint_ontology', 'endpoint_ontology_iri'],
        'value'                => ['value', 'result', 'measurement_value', 'response'],
        'unit'                 => ['unit', 'units', 'uom'],
        'unit_ontology_iri'    => ['unit_iri', 'unit_ontology_iri'],
        'timepoint_value'      => ['timepoint', 'timepoint_value', 'time', 'duration', 'time_value'],
        'timepoint_unit'       => ['timepoint_unit', 'time_unit', 'duration_unit'],
        'replicate_id'         => ['replicate', 'replicate_id', 'rep'],
        'batch_id'             => ['batch', 'batch_id', 'lot'],
        'donor_id'             => ['donor', 'donor_id', 'donor_code'],
        'device_id'            => ['device', 'device_id', 'instrument'],
        'raw_file_id'          => ['raw_file', 'raw_file_id', 'raw_data_file', 'source_file'],
        'analysis_activity_id' => ['analysis_activity', 'analysis_activity_id', 'analysis_id'],
        'qc_status'            => ['qc', 'qc_status', 'quality'],
        'exclusion_status'     => ['exclusion', 'exclusion_status', 'excluded'],
        'exclusion_reason'     => ['exclusion_reason', 'reason'],
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UnitNormalizer $unitNormalizer,
    ) {}

    /**
     * @return array{columns:string[], suggested_mapping:array<string,string>, sample_rows:array<int,array<string,string>>, row_count:int}
     */
    public function preview(string $csv, int $sampleSize = 5): array
    {
        [$headers, $rows] = $this->parseCsv($csv);
        $suggested = [];
        foreach ($headers as $header) {
            $target = $this->guessTarget($header);
            if ($target !== null) {
                $suggested[$header] = $target;
            }
        }

        $sample = [];
        foreach (array_slice($rows, 0, $sampleSize) as $row) {
            $sample[] = array_combine($headers, $row);
        }

        return [
            'columns'           => $headers,
            'suggested_mapping' => $suggested,
            'sample_rows'       => $sample,
            'row_count'         => count($rows),
        ];
    }

    /**
     * @param array<string,string> $mapping raw CSV header => canonical NAM-CORE field
     * @return array<string,mixed> validation + import summary
     */
    public function import(Project $project, string $csv, array $mapping, bool $dryRun = false): array
    {
        [$headers, $rows] = $this->parseCsv($csv);

        $errors = [];
        $warnings = [];
        $normalizations = [];
        $imported = 0;

        $mappedTargets = array_values($mapping);
        foreach (self::REQUIRED as $req) {
            if (!in_array($req, $mappedTargets, true)) {
                $errors[] = [
                    'row' => 0, 'field' => $req,
                    'message' => sprintf('Required NAM-CORE field "%s" is not mapped to any column.', $req),
                ];
            }
        }
        if (count($errors) > 0) {
            return $this->summary(0, count($rows), $errors, $warnings, $normalizations, $mapping);
        }

        foreach ($rows as $i => $row) {
            $rowNo = $i + 1;
            $record = $this->applyMapping($headers, $row, $mapping);

            $endpointId = trim((string) ($record['endpoint_id'] ?? ''));
            if ($endpointId === '') {
                $errors[] = ['row' => $rowNo, 'field' => 'endpoint_id', 'message' => 'Missing endpoint identifier.'];
                continue;
            }

            $m = new EndpointMeasurement();
            $m->setProject($project);
            $m->setEndpointId($endpointId);
            $m->setEndpointLabel(trim((string) ($record['endpoint_label'] ?? $endpointId)));
            $m->setLabel($m->getEndpointLabel() !== '' ? $m->getEndpointLabel() : $endpointId);
            $m->setEndpointOntologyIri($this->nullable($record['endpoint_ontology_iri'] ?? null));

            // value must be numeric
            $rawValue = $record['value'] ?? null;
            $m->setValueRaw($rawValue !== null ? (string) $rawValue : null);
            if ($rawValue === null || trim((string) $rawValue) === '') {
                $errors[] = ['row' => $rowNo, 'field' => 'value', 'message' => 'Missing measurement value.'];
                $m->setValidationStatus('errors');
            } elseif (!is_numeric(trim((string) $rawValue))) {
                $errors[] = ['row' => $rowNo, 'field' => 'value', 'message' => sprintf('Value "%s" is not numeric.', $rawValue)];
                $m->setValidationStatus('errors');
            } else {
                $m->setValue((float) trim((string) $rawValue));
            }

            // unit normalization
            $rawUnit = $this->nullable($record['unit'] ?? null);
            if ($rawUnit === null) {
                $warnings[] = ['row' => $rowNo, 'field' => 'unit', 'message' => 'Missing unit — must be mapped or explicitly justified before AI-ready status.'];
                $m->setValidationStatus($m->getValidationStatus() === 'errors' ? 'errors' : 'warnings');
            } else {
                $norm = $this->unitNormalizer->normalize($rawUnit);
                $m->setUnit($norm['normalized']);
                $m->setUnitOntologyIri($this->nullable($record['unit_ontology_iri'] ?? null) ?? $norm['iri']);
                if ($norm['changed']) {
                    $normalizations[] = ['row' => $rowNo, 'from' => $rawUnit, 'to' => $norm['normalized']];
                }
                if (!$norm['known']) {
                    $warnings[] = ['row' => $rowNo, 'field' => 'unit', 'message' => sprintf('Unit "%s" is not in the known-unit table; requires ontology mapping or justification.', $rawUnit)];
                }
            }

            $m->setTimepointValue($this->floatOrNull($record['timepoint_value'] ?? null));
            $m->setTimepointUnit($this->nullable($record['timepoint_unit'] ?? null));
            if ($m->getTimepointValue() !== null && $m->getTimepointUnit() === null) {
                $warnings[] = ['row' => $rowNo, 'field' => 'timepoint_unit', 'message' => 'Timepoint value present without a unit.'];
            }

            $m->setReplicateId($this->nullable($record['replicate_id'] ?? null));
            $m->setBatchId($this->nullable($record['batch_id'] ?? null));
            $m->setQcStatus($this->nullable($record['qc_status'] ?? null) ?? 'pending');
            $m->setExclusionStatus($this->nullable($record['exclusion_status'] ?? null) ?? 'included');
            $m->setExclusionReason($this->nullable($record['exclusion_reason'] ?? null));

            // resolve business-key references (soft: warn on miss, stash raw key)
            $this->resolveReferences($project, $m, $record, $rowNo, $warnings);

            if ($m->getValidationStatus() === 'unvalidated') {
                $m->setValidationStatus('valid');
            }

            if (!$dryRun) {
                $this->em->persist($m);
            }
            $imported++;
        }

        if (!$dryRun) {
            $this->em->flush();
        }

        return $this->summary($imported, count($rows), $errors, $warnings, $normalizations, $mapping);
    }

    /** @param array<string,mixed> $record */
    private function resolveReferences(Project $project, EndpointMeasurement $m, array $record, int $rowNo, array &$warnings): void
    {
        $ext = $m->getExtensions();

        if (($key = $this->nullable($record['study_id'] ?? null)) !== null) {
            $study = $this->em->getRepository(NAMStudy::class)->findOneBy(['studyId' => $key, 'project' => $project]);
            if ($study instanceof NAMStudy) { $m->setStudy($study); } else { $ext['unresolved_study_id'] = $key; }
        }
        if (($key = $this->nullable($record['sample_id'] ?? null)) !== null) {
            $sample = $this->em->getRepository(Sample::class)->findOneBy(['sampleCode' => $key, 'project' => $project]);
            if ($sample instanceof Sample) { $m->setSample($sample); } else { $ext['unresolved_sample_id'] = $key; }
        }
        if (($key = $this->nullable($record['assay_id'] ?? null)) !== null) {
            $assay = $this->em->getRepository(Assay::class)->findOneBy(['assayType' => $key, 'project' => $project])
                ?? $this->em->getRepository(Assay::class)->findOneBy(['label' => $key, 'project' => $project]);
            if ($assay instanceof Assay) { $m->setAssay($assay); } else { $ext['unresolved_assay_id'] = $key; }
        }
        if (($key = $this->nullable($record['exposure_id'] ?? null)) !== null) {
            $exposure = $this->em->getRepository(Exposure::class)->findOneBy(['testArticle' => $key, 'project' => $project]);
            if ($exposure instanceof Exposure) { $m->setExposure($exposure); } else { $ext['unresolved_exposure_id'] = $key; }
        }
        if (($key = $this->nullable($record['biological_system_id'] ?? null)) !== null) {
            $bs = $this->em->getRepository(BiologicalSystem::class)->findOneBy(['label' => $key, 'project' => $project]);
            if ($bs instanceof BiologicalSystem) { $m->setBiologicalSystem($bs); } else { $ext['unresolved_biological_system_id'] = $key; }
        }
        if (($key = $this->nullable($record['donor_id'] ?? null)) !== null) {
            $donor = $this->em->getRepository(Donor::class)->findOneBy(['donorCode' => $key, 'project' => $project]);
            if ($donor instanceof Donor) { $m->setDonor($donor); } else { $ext['unresolved_donor_id'] = $key; }
        }
        if (($key = $this->nullable($record['raw_file_id'] ?? null)) !== null) {
            $raw = $this->em->getRepository(RawDataFile::class)->findOneBy(['fileName' => $key, 'project' => $project]);
            if ($raw instanceof RawDataFile) { $m->setRawDataFile($raw); } else { $ext['unresolved_raw_file_id'] = $key; }
        }

        if ($m->getRawDataFile() === null && $m->getAnalysisActivity() === null) {
            $warnings[] = ['row' => $rowNo, 'field' => 'raw_file_id', 'message' => 'No raw data file or analysis activity linked — provenance gap.'];
        }

        if ($ext !== $m->getExtensions()) {
            $m->setExtensions($ext);
        }
    }

    /**
     * @param string[] $headers
     * @param string[] $row
     * @param array<string,string> $mapping
     * @return array<string,string>
     */
    private function applyMapping(array $headers, array $row, array $mapping): array
    {
        $assoc = array_combine($headers, $row);
        $record = [];
        foreach ($mapping as $header => $target) {
            if (isset($assoc[$header])) {
                $record[$target] = $assoc[$header];
            }
        }
        return $record;
    }

    private function guessTarget(string $header): ?string
    {
        $h = strtolower(trim($header));
        foreach (self::ALIASES as $target => $aliases) {
            if (in_array($h, $aliases, true)) {
                return $target;
            }
        }
        return null;
    }

    /**
     * @return array{0:string[], 1:array<int,string[]>}
     */
    private function parseCsv(string $csv): array
    {
        $csv = preg_replace('/^\xEF\xBB\xBF/', '', $csv) ?? $csv; // strip BOM
        $lines = preg_split('/\r\n|\r|\n/', trim($csv)) ?: [];
        $lines = array_values(array_filter($lines, static fn($l) => trim($l) !== ''));
        if (count($lines) === 0) {
            return [[], []];
        }
        $headers = array_map('trim', str_getcsv(array_shift($lines)));
        $rows = [];
        foreach ($lines as $line) {
            $cells = str_getcsv($line);
            // pad / trim to header width
            $cells = array_slice(array_pad($cells, count($headers), ''), 0, count($headers));
            $rows[] = array_map(static fn($c) => is_string($c) ? trim($c) : '', $cells);
        }
        return [$headers, $rows];
    }

    private function nullable(mixed $v): ?string
    {
        if ($v === null) { return null; }
        $s = trim((string) $v);
        return $s === '' ? null : $s;
    }

    private function floatOrNull(mixed $v): ?float
    {
        $s = $this->nullable($v);
        return $s !== null && is_numeric($s) ? (float) $s : null;
    }

    /**
     * @return array<string,mixed>
     */
    private function summary(int $imported, int $total, array $errors, array $warnings, array $normalizations, array $mapping): array
    {
        return [
            'imported'            => $imported,
            'total_rows'          => $total,
            'error_count'         => count($errors),
            'warning_count'       => count($warnings),
            'errors'              => $errors,
            'warnings'            => $warnings,
            'unit_normalizations' => $normalizations,
            'mapping'             => $mapping,
            'blocking'            => count($errors) > 0,
        ];
    }
}
