<?php

declare(strict_types=1);

namespace App\Service\NamCore;

use App\Entity\ClaimNode;
use App\Entity\ContextOfUseCard;
use App\Entity\NamCore\BiologicalSystem;
use App\Entity\NamCore\EndpointMeasurement;
use App\Entity\NamCore\Exposure;
use App\Entity\NamCore\OntologyMapping;
use App\Entity\NamCore\ProvenanceActivity;
use App\Entity\Project;

/**
 * PHP-native semantic validator mirroring standards/shacl/nam-core-v0.1.ttl.
 *
 * The optional pyshacl sidecar performs the same checks against the exported
 * JSON-LD graph for a formal RDF second opinion; this in-process evaluator makes
 * the rules queryable without the sidecar and drives the review gate. Each issue
 * carries {rule, entity, field, message, recommended_fix, severity, blocking}
 * so the frontend can navigate from a violation to the offending entity.
 */
final class SemanticValidator
{
    public function __construct(private readonly ProjectGraphBuilder $graph) {}

    /**
     * @return array<string,mixed>
     */
    public function validate(Project $project): array
    {
        $data = $this->graph->collect($project);
        $issues = [];

        $this->checkContextOfUse($data, $issues);
        $this->checkBiologicalSystems($data, $issues);
        $this->checkExposures($data, $issues);
        $this->checkEndpointMeasurements($data, $issues);
        $this->checkClaims($data, $issues);
        $this->checkProvenance($data, $issues);
        $this->checkOntology($data, $issues);

        $errors = array_values(array_filter($issues, static fn($i) => $i['severity'] === 'error'));
        $warnings = array_values(array_filter($issues, static fn($i) => $i['severity'] === 'warning'));
        $blocking = array_values(array_filter($issues, static fn($i) => $i['blocking'] === true));

        $totalChecks = max(1, count($issues) + 20);
        $completion = (int) round(100 * (1 - min(1.0, count($errors) / $totalChecks)));

        return [
            'schema'                => ProjectGraphBuilder::SCHEMA_VERSION,
            'conforms'              => count($errors) === 0,
            'error_count'           => count($errors),
            'warning_count'         => count($warnings),
            'blocking_count'        => count($blocking),
            'completion_percentage' => $completion,
            'errors'                => $errors,
            'warnings'              => $warnings,
            'issues'                => $issues,
        ];
    }

    private function add(array &$issues, string $severity, string $rule, string $entity, ?string $field, string $message, string $fix, bool $blocking): void
    {
        $issues[] = [
            'severity'        => $severity,
            'blocking'        => $blocking,
            'rule'            => $rule,
            'workspace'       => $this->workspaceFor($entity),
            'entity'          => $entity,
            'field'           => $field,
            'message'         => $message,
            'recommended_fix' => $fix,
        ];
    }

    private function workspaceFor(string $entity): string
    {
        return match (true) {
            str_starts_with($entity, 'ContextOfUse') => 'context_of_use',
            str_starts_with($entity, 'BiologicalSystem'), str_starts_with($entity, 'Exposure') => 'nam_study',
            str_starts_with($entity, 'EndpointMeasurement') => 'endpoints',
            str_starts_with($entity, 'Claim') => 'claims',
            str_starts_with($entity, 'ProvenanceActivity') => 'provenance',
            str_starts_with($entity, 'OntologyMapping') => 'ontology',
            default => 'overview',
        };
    }

    private function checkContextOfUse(array $data, array &$issues): void
    {
        /** @var ContextOfUseCard[] $cous */
        $cous = $data['contextOfUse'];
        if (count($cous) === 0) {
            $this->add($issues, 'error', 'ProjectShape', 'ContextOfUse', null,
                'Project has no Context of Use.', 'Create exactly one Context of Use card for the project.', true);
            return;
        }
        if (count($cous) > 1) {
            $this->add($issues, 'warning', 'ProjectShape', 'ContextOfUse', null,
                'Project declares more than one Context of Use.', 'A project should declare exactly one primary ContextOfUse.', false);
        }
        $cou = $cous[0];
        $required = [
            'decision_question'        => $cou->getRegulatoryQuestion(),
            'intended_use'             => $cou->getIntendedUse(),
            'biological_domain'        => $cou->getBiologicalDomain(),
            'regulatory_support_level' => $cou->getRegulatoryConfidenceLevel(),
        ];
        foreach ($required as $field => $value) {
            if (trim((string) $value) === '') {
                $this->add($issues, 'error', 'ContextOfUseShape', 'ContextOfUse:' . $cou->getCouId(), $field,
                    sprintf('ContextOfUse must include %s.', $field),
                    sprintf('Populate the "%s" field on the Context of Use card.', $field), true);
            }
        }
    }

    private function checkBiologicalSystems(array $data, array &$issues): void
    {
        /** @var BiologicalSystem[] $systems */
        $systems = $data['biologicalSystems'];
        foreach ($systems as $bs) {
            $ref = 'BiologicalSystem:' . ($bs->getLabel() !== '' ? $bs->getLabel() : $bs->getId()->toRfc4122());
            if (trim($bs->getModelSystemType()) === '') {
                $this->add($issues, 'error', 'BiologicalSystemShape', $ref, 'model_system_type',
                    'Biological system must declare model_system_type.', 'Set the model system type (organoid, organ_on_chip, …).', true);
            }
            if (trim((string) $bs->getSpeciesLabel()) === '' && $bs->getCellSource() === null) {
                $this->add($issues, 'error', 'BiologicalSystemShape', $ref, 'species',
                    'Biological system must declare a species or a cell source.', 'Add a species label or link a cell source.', true);
            }
            if (in_array($bs->getModelSystemType(), ['organoid', 'organ_on_chip', 'tissue_on_chip'], true) && $bs->getCellSource() === null) {
                $this->add($issues, 'error', 'OrganoidCellSourceShape', $ref, 'cell_source',
                    'Organoid / organ-on-chip systems must declare a cell source.', 'Link a CellSource record to this biological system.', true);
            }
            if (str_contains(strtolower($bs->getModelSystemType() . $bs->getLabel()), 'ipsc') && trim((string) $bs->getDifferentiationProtocol()) === '') {
                $this->add($issues, 'warning', 'BiologicalSystemShape', $ref, 'differentiation_protocol',
                    'iPSC-derived systems should declare a differentiation protocol.', 'Record the differentiation protocol reference.', false);
            }
            if ($bs->getCellTypeOntologyIri() === null && $bs->getSpeciesOntologyIri() === null) {
                $this->add($issues, 'warning', 'BiologicalSystemShape', $ref, 'ontology',
                    'Biological system should map to at least one ontology term.', 'Map cell type or species to an ontology term.', false);
            }
        }
    }

    private function checkExposures(array $data, array &$issues): void
    {
        /** @var Exposure[] $exposures */
        $exposures = $data['exposures'];
        foreach ($exposures as $ex) {
            $ref = 'Exposure:' . ($ex->getLabel() !== '' ? $ex->getLabel() : $ex->getId()->toRfc4122());
            if (trim($ex->getTestArticle()) === '') {
                $this->add($issues, 'error', 'ExposureShape', $ref, 'test_article',
                    'Exposure must have a test article.', 'Set the test article for this exposure.', true);
            }
            if ($ex->getConcentrationValue() !== null && trim((string) $ex->getConcentrationUnit()) === '') {
                $this->add($issues, 'error', 'ExposureShape', $ref, 'concentration_unit',
                    'Concentration present without a unit.', 'Add a concentration unit (e.g. µM).', true);
            }
            if ($ex->getTimepointValue() !== null && trim((string) $ex->getTimepointUnit()) === '') {
                $this->add($issues, 'error', 'ExposureShape', $ref, 'timepoint_unit',
                    'Timepoint present without a unit.', 'Add a timepoint unit (e.g. h).', true);
            }
        }
    }

    private function checkEndpointMeasurements(array $data, array &$issues): void
    {
        /** @var EndpointMeasurement[] $measurements */
        $measurements = $data['measurements'];
        foreach ($measurements as $m) {
            $ref = 'EndpointMeasurement:' . $m->getEndpointId() . '#' . substr($m->getId()->toRfc4122(), 0, 8);
            if ($m->getValue() === null) {
                $this->add($issues, 'error', 'EndpointMeasurementShape', $ref, 'value',
                    sprintf('Measurement value is missing or non-numeric (raw: "%s").', (string) $m->getValueRaw()),
                    'Provide a numeric value or exclude the row with a reason.', true);
            }
            if (trim((string) $m->getUnit()) === '') {
                $this->add($issues, 'error', 'EndpointMeasurementShape', $ref, 'unit',
                    'Endpoint measurement has no unit.', 'Map or justify a unit for this endpoint.', true);
            }
            if (trim((string) $m->getEndpointId()) === '') {
                $this->add($issues, 'error', 'EndpointMeasurementShape', $ref, 'endpoint',
                    'Endpoint measurement is not linked to an endpoint definition.', 'Set an endpoint identifier/label.', true);
            }
            if ($m->getRawDataFile() === null && $m->getAnalysisActivity() === null) {
                $this->add($issues, 'error', 'EndpointMeasurementShape', $ref, 'provenance',
                    'Endpoint measurement links to neither a raw data file nor an analysis activity.',
                    'Link a raw data file or an analysis activity for provenance.', true);
            }
            if ($m->getEndpointOntologyIri() === null) {
                $this->add($issues, 'warning', 'EndpointMeasurementShape', $ref, 'endpoint_ontology_iri',
                    sprintf('Endpoint "%s" is not mapped to an ontology term.', $m->getEndpointId()),
                    'Map the endpoint to OBI/NCIT or the internal NAM endpoint vocabulary.', false);
            }
        }
    }

    private function checkClaims(array $data, array &$issues): void
    {
        /** @var ClaimNode[] $claims */
        $claims = $data['claims'];
        foreach ($claims as $claim) {
            $ref = 'Claim:' . $claim->getClaimId();
            if (trim($claim->getReviewStatus()) === '') {
                $this->add($issues, 'error', 'EvidenceClaimShape', $ref, 'review_status',
                    'Claim has no review status.', 'Set a review status on the claim.', true);
            }
            if (in_array($claim->getConfidence(), ['decision_informing', 'potentially_pivotal'], true)
                && count($claim->getSupportingEvidence()) === 0) {
                $this->add($issues, 'error', 'EvidenceClaimShape', $ref, 'supporting_evidence',
                    'Decision-informing / potentially-pivotal claim has no linked validation evidence.',
                    'Link supporting validation evidence to this claim.', true);
            }
            if ($claim->getReviewStatus() === 'human_review_required') {
                $this->add($issues, 'error', 'EvidenceClaimShape', $ref, 'review_status',
                    'Claim still requires human review — blocks formal export.',
                    'Complete human review and approve or reject the claim.', true);
            }
        }
    }

    private function checkProvenance(array $data, array &$issues): void
    {
        /** @var ProvenanceActivity[] $activities */
        $activities = $data['activities'];
        foreach ($activities as $act) {
            $ref = 'ProvenanceActivity:' . ($act->getLabel() !== '' ? $act->getLabel() : $act->getId()->toRfc4122());
            if (trim((string) $act->getSoftwareName()) === '' && trim((string) $act->getScriptReference()) === '') {
                $this->add($issues, 'error', 'AnalysisActivityShape', $ref, 'software',
                    'Analysis activity lacks a software name or script reference.',
                    'Record the software name or script/repository reference.', true);
            }
        }
    }

    private function checkOntology(array $data, array &$issues): void
    {
        /** @var OntologyMapping[] $mappings */
        $mappings = $data['ontologyMappings'];
        foreach ($mappings as $map) {
            if ($map->isMandatory() && $map->getMappingStatus() !== OntologyMapping::STATUS_APPROVED) {
                $this->add($issues, 'error', 'OntologyMappingShape', 'OntologyMapping:' . $map->getSourceValue(), 'mapping_status',
                    sprintf('Mandatory ontology mapping "%s" is not approved (status: %s).', $map->getSourceValue(), $map->getMappingStatus()),
                    'Review and approve the ontology mapping, or reject and remap.', true);
            }
        }
    }
}
