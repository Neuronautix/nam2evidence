<?php

declare(strict_types=1);

namespace App\Service\NamCore;

use App\Entity\ContextOfUseCard;
use App\Entity\NamCore\EndpointMeasurement;
use App\Entity\NAMStudy;
use App\Entity\Project;

/**
 * Basic ISA-Tab exporter (Investigation / Study / Assay) for life-science
 * metadata interoperability. This is a lightweight, tab-delimited POC mapping —
 * ISA-Tab is for experimental metadata interoperability, NOT a regulatory
 * submission format.
 *
 *   Project           → Investigation
 *   NAMStudy          → Study
 *   Assay/Endpoint    → Assay
 *   Sample            → Source / Sample
 *   Exposure          → Protocol REF / Parameter Value
 *   EndpointMeasurement → Assay outputs
 *   OntologyTerm      → Term Source REF / Term Accession Number
 */
final class IsaTabExporter
{
    public function __construct(private readonly ProjectGraphBuilder $graph) {}

    /**
     * @return array<string,string> filename => TSV content
     */
    public function export(Project $project): array
    {
        $data = $this->graph->collect($project);
        /** @var ContextOfUseCard[] $cous */
        $cous = $data['contextOfUse'];
        $cou = $cous[0] ?? null;
        /** @var NAMStudy[] $studies */
        $studies = $data['studies'];
        $study = $studies[0] ?? null;

        return [
            'i_investigation.txt' => $this->investigation($project, $cou, $study),
            's_study.txt'         => $this->studyFile($data),
            'a_assay.txt'         => $this->assayFile($data),
        ];
    }

    private function line(string $label, string ...$values): string
    {
        return $label . "\t" . implode("\t", array_map(static fn($v) => '"' . str_replace('"', '', $v) . '"', $values)) . "\n";
    }

    private function investigation(Project $project, ?ContextOfUseCard $cou, ?NAMStudy $study): string
    {
        $out = "ONTOLOGY SOURCE REFERENCE\n";
        $out .= $this->line('Term Source Name', 'CL', 'UBERON', 'CHEBI', 'OBI', 'MONDO', 'NCIT', 'UO', 'NCBITaxon');
        $out .= $this->line('Term Source File', 'http://purl.obolibrary.org/obo/cl.owl', 'http://purl.obolibrary.org/obo/uberon.owl', 'http://purl.obolibrary.org/obo/chebi.owl', 'http://purl.obolibrary.org/obo/obi.owl', 'http://purl.obolibrary.org/obo/mondo.owl', 'http://purl.obolibrary.org/obo/ncit.owl', 'http://purl.obolibrary.org/obo/uo.owl', 'http://purl.obolibrary.org/obo/ncbitaxon.owl');
        $out .= $this->line('Term Source Description', 'Cell Ontology', 'Uberon', 'ChEBI', 'Ontology for Biomedical Investigations', 'MONDO', 'NCI Thesaurus', 'Units of Measurement Ontology', 'NCBI Taxonomy');
        $out .= "INVESTIGATION\n";
        $out .= $this->line('Investigation Identifier', $project->getId()->toRfc4122());
        $out .= $this->line('Investigation Title', $project->getName());
        $out .= $this->line('Investigation Description', (string) $project->getDescription());
        $out .= $this->line('Comment[NAM-CORE schema version]', ProjectGraphBuilder::SCHEMA_VERSION);
        $out .= $this->line('Comment[Standardization POC]', 'ISA-Tab for experimental metadata interoperability, not a regulatory submission format.');
        $out .= "STUDY\n";
        $out .= $this->line('Study Identifier', $study?->getStudyId() ?? '');
        $out .= $this->line('Study Title', $study?->getTitle() ?? '');
        $out .= $this->line('Study File Name', 's_study.txt');
        if ($cou !== null) {
            $out .= $this->line('Comment[Context of Use]', $cou->getCouId());
            $out .= $this->line('Comment[Decision Question]', $cou->getRegulatoryQuestion());
            $out .= $this->line('Comment[Regulatory Support Level]', $cou->getRegulatoryConfidenceLevel());
        }
        $out .= "STUDY ASSAYS\n";
        $out .= $this->line('Study Assay File Name', 'a_assay.txt');
        $out .= $this->line('Study Assay Measurement Type', 'in vitro toxicity endpoint');
        $out .= "STUDY PROTOCOLS\n";
        $out .= $this->line('Study Protocol Name', 'exposure', 'endpoint measurement');
        $out .= $this->line('Study Protocol Type', 'treatment', 'data acquisition');
        return $out;
    }

    private function studyFile(array $data): string
    {
        $headers = ['Source Name', 'Characteristics[species]', 'Characteristics[model system type]', 'Sample Name', 'Characteristics[batch]', 'Characteristics[replicate]'];
        $rows = [implode("\t", $headers)];

        $bioSystems = $data['biologicalSystems'];
        $systemLabel = 'biological-system';
        $species = '';
        $modelType = '';
        if (count($bioSystems) > 0) {
            $bs = $bioSystems[0];
            $systemLabel = $bs->getLabel() !== '' ? $bs->getLabel() : $systemLabel;
            $species = (string) $bs->getSpeciesLabel();
            $modelType = $bs->getModelSystemType();
        }

        $samples = $data['samples'];
        if (count($samples) === 0) {
            $rows[] = implode("\t", [$systemLabel, $species, $modelType, 'sample-1', '', '']);
        }
        foreach ($samples as $sample) {
            $rows[] = implode("\t", [
                $systemLabel,
                $species,
                $modelType,
                $sample->getSampleCode(),
                (string) $sample->getBatchId(),
                (string) $sample->getReplicateId(),
            ]);
        }
        return implode("\n", $rows) . "\n";
    }

    private function assayFile(array $data): string
    {
        $headers = [
            'Sample Name', 'Protocol REF', 'Parameter Value[test article]', 'Parameter Value[concentration]',
            'Parameter Value[concentration unit]', 'Parameter Value[timepoint]', 'Parameter Value[timepoint unit]',
            'Assay Name', 'Endpoint', 'Term Accession Number', 'Raw Data File', 'Value', 'Unit', 'QC Status',
        ];
        $rows = [implode("\t", $headers)];

        /** @var EndpointMeasurement[] $measurements */
        $measurements = $data['measurements'];
        foreach ($measurements as $m) {
            $ex = $m->getExposure();
            $rows[] = implode("\t", array_map(static fn($v) => (string) $v, [
                $m->getSample()?->getSampleCode() ?? '',
                'endpoint measurement',
                $ex?->getTestArticle() ?? '',
                $ex?->getConcentrationValue() ?? '',
                $ex?->getConcentrationUnit() ?? '',
                $m->getTimepointValue() ?? '',
                $m->getTimepointUnit() ?? '',
                $m->getAssay()?->getLabel() ?? $m->getEndpointId(),
                $m->getEndpointLabel(),
                $m->getEndpointOntologyIri() ?? '',
                $m->getRawDataFile()?->getFileName() ?? '',
                $m->getValue() ?? '',
                $m->getUnit() ?? '',
                $m->getQcStatus(),
            ]));
        }
        if (count($measurements) === 0) {
            $rows[] = implode("\t", array_fill(0, count($headers), ''));
        }
        return implode("\n", $rows) . "\n";
    }
}
