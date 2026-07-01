<?php

declare(strict_types=1);

namespace App\Controller\V1;

use App\Entity\Project;
use App\Service\Export\ExportReadinessGate;
use App\Service\NamCore\AuditLogger;
use App\Service\NamCore\IsaTabExporter;
use App\Service\NamCore\ProjectGraphBuilder;
use App\Service\NamCore\ReadinessScorer;
use App\Service\NamCore\RoCrateBuilder;
use App\Service\NamCore\SemanticValidator;
use App\Service\NamCore\TurtleSerializer;
use App\Service\NamCore\ValidatorSidecarClient;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Ulid;

/**
 * NAM-CORE reusable exports (v1): JSON-LD, RDF/Turtle, RO-Crate (ZIP),
 * ISA-Tab (ZIP), Parquet (ZIP).
 *
 * Formal package formats (RO-Crate) are subject to the aggregated review gate;
 * data-level serializations (JSON-LD, Turtle, ISA-Tab, Parquet) are permitted as
 * standardization artifacts and carry conservative POC disclaimers.
 */
#[Route('/api/v1/projects/{id}/exports')]
class NamCoreExportController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ProjectGraphBuilder $graph,
        private readonly TurtleSerializer $turtle,
        private readonly IsaTabExporter $isaTab,
        private readonly RoCrateBuilder $roCrate,
        private readonly SemanticValidator $validator,
        private readonly ReadinessScorer $scorer,
        private readonly ExportReadinessGate $gate,
        private readonly ValidatorSidecarClient $sidecar,
        private readonly AuditLogger $audit,
    ) {}

    #[Route('/jsonld', name: 'v1_export_jsonld', methods: ['GET'])]
    public function jsonld(string $id): Response
    {
        $project = $this->requireProject($id);
        if ($project instanceof JsonResponse) { return $project; }

        $doc = $this->graph->toJsonLd($project);
        $this->audit->log($project, 'ExportPackage', null, 'export', null, ['format' => 'jsonld'], 'JSON-LD export');

        return $this->fileResponse(
            json_encode($doc, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}',
            'application/ld+json',
            $this->slug($project) . '.jsonld',
        );
    }

    #[Route('/turtle', name: 'v1_export_turtle', methods: ['GET'])]
    public function turtle(string $id): Response
    {
        $project = $this->requireProject($id);
        if ($project instanceof JsonResponse) { return $project; }

        $ttl = $this->turtle->serialize($this->graph->toJsonLd($project));
        $this->audit->log($project, 'ExportPackage', null, 'export', null, ['format' => 'turtle'], 'RDF/Turtle export');

        return $this->fileResponse($ttl, 'text/turtle', $this->slug($project) . '.ttl');
    }

    #[Route('/isa-tab', name: 'v1_export_isatab', methods: ['GET'])]
    public function isatab(string $id): Response
    {
        $project = $this->requireProject($id);
        if ($project instanceof JsonResponse) { return $project; }

        $files = $this->isaTab->export($project);
        $this->audit->log($project, 'ExportPackage', null, 'export', null, ['format' => 'isa-tab'], 'ISA-Tab export');

        return $this->zip($files, $this->slug($project) . '_isa-tab.zip');
    }

    #[Route('/parquet', name: 'v1_export_parquet', methods: ['GET'])]
    public function parquet(string $id): Response
    {
        $project = $this->requireProject($id);
        if ($project instanceof JsonResponse) { return $project; }

        $tables = $this->graph->toTables($project);

        // Preferred: real Parquet from the pyarrow sidecar. Fallback: CSV-in-ZIP
        // so the endpoint always yields a computable artifact.
        $parquetZip = $this->sidecar->parquet($tables);
        $this->audit->log($project, 'ExportPackage', null, 'export', null, ['format' => 'parquet', 'engine' => $parquetZip !== null ? 'parquet' : 'csv-fallback'], 'Parquet export');

        if ($parquetZip !== null) {
            return new Response($parquetZip, Response::HTTP_OK, [
                'Content-Type'        => 'application/zip',
                'Content-Disposition' => sprintf('attachment; filename="%s_parquet.zip"', $this->slug($project)),
            ]);
        }

        $csvFiles = [];
        foreach ($tables as $name => $rows) {
            $csvFiles[$name . '.csv'] = $this->toCsv($rows);
        }
        $csvFiles['README.txt'] = "Parquet sidecar (pyarrow) not configured; emitting CSV equivalents.\n"
            . "Set VALIDATOR_URL to the validator service to receive native .parquet files.\n";

        return $this->zip($csvFiles, $this->slug($project) . '_parquet-fallback-csv.zip');
    }

    #[Route('/ro-crate', name: 'v1_export_rocrate', methods: ['GET'])]
    public function roCrate(string $id): Response
    {
        $project = $this->requireProject($id);
        if ($project instanceof JsonResponse) { return $project; }

        $gate = $this->gate->evaluate($project);
        $complete = !$gate['blocked'];

        $jsonld = $this->graph->toJsonLd($project);
        $tables = $this->graph->toTables($project);
        $validation = $this->validator->validate($project);
        $readiness = $this->scorer->score($project);

        $files = [];
        $files['nam-core.json']        = $this->jsonStr(['schema' => ProjectGraphBuilder::SCHEMA_VERSION, 'graph' => $jsonld['@graph']]);
        $files['metadata.jsonld']      = $this->jsonStr($jsonld);
        $files['endpoint_measurements.csv'] = $this->toCsv($tables['endpoint_measurements']);
        $files['validation-report.json'] = $this->jsonStr($validation);
        $files['readiness-report.json'] = $this->jsonStr($readiness);
        $files['dossier.md']           = $this->dossier($project, $validation, $readiness, $gate);
        $files['ectd-mapping.txt']     = $this->ectdTxt($project);
        $files['provenance.json']      = $this->jsonStr($this->provenance($project));

        $parts = [
            ['path' => 'nam-core.json', 'type' => 'File', 'description' => 'Canonical NAM-CORE graph', 'encodingFormat' => 'application/json'],
            ['path' => 'metadata.jsonld', 'type' => 'File', 'description' => 'NAM-CORE JSON-LD metadata', 'encodingFormat' => 'application/ld+json'],
            ['path' => 'endpoint_measurements.csv', 'type' => 'File', 'description' => 'Canonical endpoint measurements', 'encodingFormat' => 'text/csv'],
            ['path' => 'validation-report.json', 'type' => 'File', 'description' => 'Semantic validation report', 'encodingFormat' => 'application/json'],
            ['path' => 'readiness-report.json', 'type' => 'File', 'description' => 'POC FAIR/AI-readiness assessment', 'encodingFormat' => 'application/json'],
            ['path' => 'dossier.md', 'type' => 'File', 'description' => 'Markdown evidence dossier', 'encodingFormat' => 'text/markdown'],
            ['path' => 'ectd-mapping.txt', 'type' => 'File', 'description' => 'eCTD Module 4 mapping proposal', 'encodingFormat' => 'text/plain'],
            ['path' => 'provenance.json', 'type' => 'File', 'description' => 'PROV-inspired provenance metadata', 'encodingFormat' => 'application/json'],
        ];
        $metadata = $this->roCrate->metadata($project->getName(), (string) $project->getDescription(), $parts);
        $metadata['@graph'][1]['nam:packageStatus'] = $complete ? 'complete' : 'draft';
        $files['ro-crate-metadata.json'] = $this->jsonStr($metadata);

        $this->audit->log($project, 'ExportPackage', null, 'export', null, ['format' => 'ro-crate', 'status' => $complete ? 'complete' : 'draft'], 'RO-Crate export');

        return $this->zip($files, $this->slug($project) . '_ro-crate.zip');
    }

    // ── helpers ────────────────────────────────────────────────────────────

    private function provenance(Project $project): array
    {
        $data = $this->graph->collect($project);
        $raw = array_map(static fn($f) => [
            '@type' => 'RawDataFile', 'name' => $f->getFileName(), 'checksum' => $f->getChecksum(),
            'sourceSystem' => $f->getSourceSystem(), 'uploadDate' => $f->getUploadDate()?->format(\DateTimeInterface::ATOM),
        ], $data['rawFiles']);
        $act = array_map(static fn($a) => [
            '@type' => 'prov:Activity', 'name' => $a->getLabel(), 'softwareName' => $a->getSoftwareName(),
            'softwareVersion' => $a->getSoftwareVersion(), 'scriptReference' => $a->getScriptReference(),
            'agent' => $a->getAgentName(), 'generatedAtTime' => $a->getEndedAt()?->format(\DateTimeInterface::ATOM),
        ], $data['activities']);
        return ['raw_data_files' => $raw, 'activities' => $act];
    }

    private function dossier(Project $project, array $validation, array $readiness, array $gate): string
    {
        $lines = [
            '# NAM-CORE Evidence Dossier',
            '',
            '> POC standardization package. Not an official regulatory submission, SEND/CDISC deliverable, or claim of validation. Requires qualified human review.',
            '',
            '**Project:** ' . $project->getName(),
            '**Drug:** ' . $project->getDrugName(),
            '**Schema:** ' . ProjectGraphBuilder::SCHEMA_VERSION,
            '',
            '## Standardization status',
            '- Semantic validation errors: ' . $validation['error_count'],
            '- Semantic validation warnings: ' . $validation['warning_count'],
            '- POC FAIR/AI-readiness: ' . $readiness['total_score'] . '/' . $readiness['max_score'] . ' (' . $readiness['percentage'] . '%)',
            '- Export gate: ' . ($gate['blocked'] ? 'BLOCKED — ' . $gate['blocker_count'] . ' blocker(s)' : 'internally reviewed'),
            '',
            '## Readiness dimensions',
        ];
        foreach ($readiness['dimensions'] as $d) {
            $lines[] = '- ' . $d['label'] . ': ' . $d['score'] . '/2';
        }
        return implode("\n", $lines) . "\n";
    }

    private function ectdTxt(Project $project): string
    {
        return "eCTD Module 4 mapping proposal\n"
            . "Drug: " . $project->getDrugName() . "\n\n"
            . "NAM evidence placement depends on context of use and regulatory strategy.\n"
            . "This mapping is a structured proposal, not regulatory advice.\n\n"
            . "See GET /api/projects/{id}/ectd for the structured mapping records.\n";
    }

    private function requireProject(string $id): Project|JsonResponse
    {
        try {
            $p = $this->em->find(Project::class, Ulid::fromString($id)->toRfc4122());
        } catch (\Throwable) {
            $p = null;
        }
        return $p ?? $this->json(['error' => 'Project not found'], Response::HTTP_NOT_FOUND);
    }

    private function slug(Project $project): string
    {
        $s = preg_replace('/[^a-z0-9]+/', '-', strtolower($project->getDrugName())) ?? 'project';
        return trim($s, '-') !== '' ? trim($s, '-') : 'project';
    }

    private function jsonStr(mixed $v): string
    {
        return json_encode($v, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';
    }

    /** @param list<array<string,mixed>> $rows */
    private function toCsv(array $rows): string
    {
        if (count($rows) === 0) {
            return "\n";
        }
        $headers = array_keys($rows[0]);
        $out = [implode(',', array_map($this->csvCell(...), $headers))];
        foreach ($rows as $row) {
            $out[] = implode(',', array_map(fn($h) => $this->csvCell((string) ($row[$h] ?? '')), $headers));
        }
        return implode("\n", $out) . "\n";
    }

    private function csvCell(string $v): string
    {
        return '"' . str_replace('"', '""', $v) . '"';
    }

    private function fileResponse(string $content, string $mime, string $filename): Response
    {
        return new Response($content, Response::HTTP_OK, [
            'Content-Type'        => $mime,
            'Content-Disposition' => sprintf('attachment; filename="%s"', $filename),
        ]);
    }

    /** @param array<string,string> $files */
    private function zip(array $files, string $filename): Response
    {
        $tmp = tempnam(sys_get_temp_dir(), 'namcore_zip_');
        if ($tmp === false) {
            return $this->json(['error' => 'Could not allocate archive'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
        $zip = new \ZipArchive();
        $zip->open($tmp, \ZipArchive::OVERWRITE);
        foreach ($files as $name => $content) {
            $zip->addFromString($name, $content);
        }
        $zip->close();
        $bytes = (string) file_get_contents($tmp);
        @unlink($tmp);

        return new Response($bytes, Response::HTTP_OK, [
            'Content-Type'        => 'application/zip',
            'Content-Disposition' => sprintf('attachment; filename="%s"', $filename),
        ]);
    }
}
