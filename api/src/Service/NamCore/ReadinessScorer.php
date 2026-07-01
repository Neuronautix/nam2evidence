<?php

declare(strict_types=1);

namespace App\Service\NamCore;

use App\Entity\ClaimNode;
use App\Entity\ContextOfUseCard;
use App\Entity\NamCore\BiologicalSystem;
use App\Entity\NamCore\EndpointMeasurement;
use App\Entity\NamCore\OntologyMapping;
use App\Entity\NamCore\ProvenanceActivity;
use App\Entity\Project;

/**
 * POC FAIR / AI-readiness assessment.
 *
 * Ten dimensions, each scored 0 (absent/unusable) / 1 (partial) / 2 (complete
 * enough for the POC standard). This is an internal maturity heuristic — it is
 * explicitly NOT a certified score, regulatory endorsement, or claim of
 * validation. Labelled "POC FAIR/AI-readiness assessment" everywhere it renders.
 */
final class ReadinessScorer
{
    public const DIMENSIONS = [
        'fairness'                   => 'FAIRness',
        'provenance'                 => 'Provenance',
        'biological_characterization'=> 'Biological characterization',
        'technical_characterization' => 'Technical characterization',
        'controlled_terminology'     => 'Controlled terminology',
        'data_integrity'             => 'Data integrity',
        'computability'              => 'Computability',
        'pre_model_explainability'   => 'Pre-model explainability',
        'sustainability'             => 'Sustainability',
        'regulatory_traceability'    => 'Regulatory traceability',
    ];

    public function __construct(
        private readonly ProjectGraphBuilder $graph,
        private readonly SemanticValidator $validator,
    ) {}

    /**
     * @return array<string,mixed>
     */
    public function score(Project $project): array
    {
        $data = $this->graph->collect($project);
        $validation = $this->validator->validate($project);

        $measurements = $data['measurements'];
        $mappings = $data['ontologyMappings'];
        $activities = $data['activities'];
        $bioSystems = $data['biologicalSystems'];
        /** @var ContextOfUseCard[] $cous */
        $cous = $data['contextOfUse'];
        $cou = $cous[0] ?? null;

        $mappedCount = count(array_filter($mappings, static fn(OntologyMapping $m) => $m->getMappingStatus() === OntologyMapping::STATUS_APPROVED));
        $withIri = count(array_filter($measurements, static fn(EndpointMeasurement $m) => $m->getEndpointOntologyIri() !== null));
        $withProvenance = count(array_filter($measurements, static fn(EndpointMeasurement $m) => $m->getRawDataFile() !== null || $m->getAnalysisActivity() !== null));
        $numericValues = count(array_filter($measurements, static fn(EndpointMeasurement $m) => $m->getValue() !== null));
        $totalM = max(1, count($measurements));

        $dimensions = [];

        $dimensions['fairness'] = $this->band(
            $withIri / $totalM,
            'Findable/Interoperable identifiers and ontology IRIs on endpoint measurements.',
            $withIri === 0 ? 'Map endpoints and units to ontology IRIs; enable JSON-LD/RDF export.' : 'Map remaining endpoints/units to ontology terms.'
        );

        $dimensions['provenance'] = $this->band(
            count($measurements) === 0 ? 0.0 : $withProvenance / $totalM,
            'Each processed measurement derives from a raw file or analysis activity.',
            'Attach raw data files / analysis activities to measurements lacking provenance.'
        );

        $bioComplete = 0.0;
        if (count($bioSystems) > 0) {
            $ok = 0;
            foreach ($bioSystems as $bs) {
                /** @var BiologicalSystem $bs */
                $has = trim($bs->getModelSystemType()) !== '' && ($bs->getSpeciesLabel() !== null || $bs->getCellSource() !== null);
                if ($has) { $ok++; }
            }
            $bioComplete = $ok / count($bioSystems);
        }
        $dimensions['biological_characterization'] = $this->band(
            $bioComplete,
            'Model system type, species/cell source and differentiation characterized.',
            'Complete biological system metadata (model type, species, cell source, protocol).'
        );

        $techScore = count($data['assays']) > 0 ? 1.0 : 0.0;
        if (count($data['assays']) > 0 && count($data['exposures']) > 0) { $techScore = 2.0; }
        $dimensions['technical_characterization'] = $this->fixed(
            (int) $techScore,
            'Assays, platforms/devices and exposures described.',
            'Add assay, device and exposure records linked to measurements.'
        );

        $dimensions['controlled_terminology'] = $this->band(
            count($mappings) === 0 ? 0.0 : $mappedCount / max(1, count($mappings)),
            'Source terms mapped to controlled vocabularies and human-approved.',
            'Approve suggested ontology mappings; resolve mandatory unmapped terms.'
        );

        $integrityErrors = $validation['error_count'];
        $dimensions['data_integrity'] = $this->fixed(
            $integrityErrors === 0 ? 2 : ($integrityErrors <= 3 ? 1 : 0),
            'Semantic validation passes without blocking structural errors.',
            'Resolve semantic validation errors (missing units/values/provenance).'
        );

        $dimensions['computability'] = $this->band(
            $numericValues / $totalM,
            'Machine-readable numeric values and tabular/Parquet export available.',
            'Ensure endpoint values are numeric and units normalized for computation.'
        );

        $explain = 0;
        if ($cou !== null && count($cou->getLimitations()) > 0) { $explain++; }
        if (count(array_filter($data['claims'], static fn(ClaimNode $c) => count($c->getLimitations()) > 0)) > 0) { $explain++; }
        $dimensions['pre_model_explainability'] = $this->fixed(
            $explain,
            'Documented limitations and claim caveats support interpretability.',
            'Document model limitations and per-claim caveats.'
        );

        $hasScript = count(array_filter($activities, static fn(ProvenanceActivity $a) => $a->getScriptReference() !== null || $a->getSoftwareName() !== null)) > 0;
        $dimensions['sustainability'] = $this->fixed(
            $hasScript ? ($mappedCount > 0 ? 2 : 1) : 0,
            'Version-controlled analysis references and reusable standardized exports.',
            'Record analysis script references and generate reusable exports (RO-Crate, ISA-Tab).'
        );

        $regScore = 0;
        if ($cou !== null && trim($cou->getRegulatoryQuestion()) !== '') { $regScore++; }
        if (count(array_filter($data['claims'], static fn(ClaimNode $c) => count($c->getEctdTargetSections()) > 0)) > 0) { $regScore++; }
        $dimensions['regulatory_traceability'] = $this->fixed(
            $regScore,
            'Context of Use plus claim-to-eCTD-section traceability present.',
            'Complete the Context of Use and map claims to eCTD sections.'
        );

        $out = [];
        $total = 0;
        $blockingGaps = [];
        $improvements = [];
        foreach (self::DIMENSIONS as $key => $label) {
            $d = $dimensions[$key];
            $out[] = [
                'key'         => $key,
                'label'       => $label,
                'score'       => $d['score'],
                'max'         => 2,
                'rationale'   => $d['rationale'],
                'improvement' => $d['improvement'],
            ];
            $total += $d['score'];
            if ($d['score'] === 0) {
                $blockingGaps[] = $label;
            }
            if ($d['score'] < 2) {
                $improvements[] = ['dimension' => $label, 'action' => $d['improvement']];
            }
        }

        $maxTotal = 2 * count(self::DIMENSIONS);

        return [
            'label'          => 'POC FAIR/AI-readiness assessment',
            'disclaimer'     => 'This is a proof-of-concept maturity heuristic, not a certified score, regulatory endorsement, or claim of scientific validation.',
            'total_score'    => $total,
            'max_score'      => $maxTotal,
            'percentage'     => (int) round(100 * $total / $maxTotal),
            'dimensions'     => $out,
            'blocking_gaps'  => $blockingGaps,
            'recommended_improvements' => $improvements,
            'validation_summary' => [
                'errors'   => $validation['error_count'],
                'warnings' => $validation['warning_count'],
                'blocking' => $validation['blocking_count'],
            ],
        ];
    }

    /**
     * @return array{score:int, rationale:string, improvement:string}
     */
    private function band(float $ratio, string $rationale, string $improvement): array
    {
        $score = $ratio >= 0.99 ? 2 : ($ratio > 0.0 ? 1 : 0);
        return ['score' => $score, 'rationale' => $rationale, 'improvement' => $improvement];
    }

    /**
     * @return array{score:int, rationale:string, improvement:string}
     */
    private function fixed(int $score, string $rationale, string $improvement): array
    {
        return ['score' => max(0, min(2, $score)), 'rationale' => $rationale, 'improvement' => $improvement];
    }
}
