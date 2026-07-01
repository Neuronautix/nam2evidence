<?php

declare(strict_types=1);

namespace App\Service\NamCore;

use App\Entity\ClaimNode;
use App\Entity\ContextOfUseCard;
use App\Entity\EvidenceItem;
use App\Entity\NamCore\Assay;
use App\Entity\NamCore\BiologicalSystem;
use App\Entity\NamCore\EndpointMeasurement;
use App\Entity\NamCore\Exposure;
use App\Entity\NamCore\OntologyMapping;
use App\Entity\NamCore\ProvenanceActivity;
use App\Entity\NamCore\RawDataFile;
use App\Entity\NamCore\Sample;
use App\Entity\NAMStudy;
use App\Entity\Project;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Collects the full NAM-CORE + legacy view of a project into three shapes reused
 * across the toolkit:
 *   - collect()   : a plain structured PHP array (RO-Crate / Markdown / audit)
 *   - toJsonLd()  : a JSON-LD document (stable @id, nam: types) for JSON-LD,
 *                   Turtle, and SHACL semantic validation
 *   - toTables()  : flat row arrays for Parquet / ISA-Tab / CSV
 *
 * This is a standardization view, not a regulatory assertion. All @id values are
 * stable within a project namespace so exports round-trip and cross-reference.
 */
final class ProjectGraphBuilder
{
    public const SCHEMA_VERSION = 'NAM-CORE v0.1';
    private const CONTEXT_URL = 'https://w3id.org/nam-core/0.1/context.jsonld';

    public function __construct(private readonly EntityManagerInterface $em) {}

    private function base(Project $project): string
    {
        return 'https://w3id.org/nam-core/project/' . $project->getId()->toRfc4122() . '/';
    }

    /**
     * @return array<string,mixed>
     */
    public function collect(Project $project): array
    {
        $projectId = $project->getId()->toRfc4122();
        $cous = $this->em->getRepository(ContextOfUseCard::class)->findBy(['project' => $project]);
        $studies = $this->em->getRepository(NAMStudy::class)->findBy(['project' => $project]);
        $claims = $this->em->getRepository(ClaimNode::class)->findBy(['project' => $project]);
        $bioSystems = $this->em->getRepository(BiologicalSystem::class)->findBy(['project' => $project]);
        $samples = $this->em->getRepository(Sample::class)->findBy(['project' => $project]);
        $exposures = $this->em->getRepository(Exposure::class)->findBy(['project' => $project]);
        $assays = $this->em->getRepository(Assay::class)->findBy(['project' => $project]);
        $measurements = $this->em->getRepository(EndpointMeasurement::class)->findBy(['project' => $project]);
        $rawFiles = $this->em->getRepository(RawDataFile::class)->findBy(['project' => $project]);
        $activities = $this->em->getRepository(ProvenanceActivity::class)->findBy(['project' => $project]);
        $mappings = $this->em->getRepository(OntologyMapping::class)->findBy(['project' => $project]);

        $evidence = [];
        foreach ($studies as $study) {
            foreach ($study->getEvidenceItems() as $item) {
                $evidence[] = $item;
            }
        }

        return [
            'project'          => $project,
            'contextOfUse'     => $cous,
            'studies'          => $studies,
            'claims'           => $claims,
            'evidence'         => $evidence,
            'biologicalSystems'=> $bioSystems,
            'samples'          => $samples,
            'exposures'        => $exposures,
            'assays'           => $assays,
            'measurements'     => $measurements,
            'rawFiles'         => $rawFiles,
            'activities'       => $activities,
            'ontologyMappings' => $mappings,
        ];
    }

    /**
     * @return array<string,mixed> a JSON-LD document with @context + @graph
     */
    public function toJsonLd(Project $project): array
    {
        $data = $this->collect($project);
        $base = $this->base($project);
        $graph = [];

        $couNode = null;
        /** @var ContextOfUseCard[] $cous */
        $cous = $data['contextOfUse'];
        if (count($cous) > 0) {
            $cou = $cous[0];
            $couNode = $base . 'cou/' . $cou->getCouId();
            $graph[] = array_filter([
                '@id'                    => $couNode,
                '@type'                  => 'ContextOfUse',
                'label'                  => $cou->getCouId(),
                'decisionQuestion'       => $cou->getRegulatoryQuestion(),
                'intendedUse'            => $cou->getIntendedUse(),
                'biologicalDomain'       => $cou->getBiologicalDomain(),
                'regulatorySupportLevel' => $cou->getRegulatoryConfidenceLevel(),
                'version'                => $cou->getVersion(),
                'reviewStatus'           => $cou->getReviewStatus(),
            ], static fn($v) => $v !== null && $v !== '');
        }

        // Project / evidence package root
        $graph[] = array_filter([
            '@id'          => $base . 'package',
            '@type'        => 'EvidencePackage',
            'label'        => $project->getName(),
            'description'  => $project->getDescription(),
            'project'      => $base . 'project',
        ], static fn($v) => $v !== null && $v !== '');

        $graph[] = array_filter([
            '@id'         => $base . 'project',
            '@type'       => 'Project',
            'label'       => $project->getName(),
            'description' => $project->getDescription(),
            'contextOfUse'=> $couNode,
        ], static fn($v) => $v !== null && $v !== '');

        /** @var BiologicalSystem $bs */
        foreach ($data['biologicalSystems'] as $bs) {
            $graph[] = array_filter([
                '@id'             => $base . 'biosystem/' . $bs->getId()->toRfc4122(),
                '@type'           => 'BiologicalSystem',
                'label'           => $bs->getLabel(),
                'modelSystemType' => $bs->getModelSystemType(),
                'species'         => $bs->getSpeciesOntologyIri(),
                'anatomy'         => $bs->getAnatomyOntologyIri(),
                'cellType'        => $bs->getCellTypeOntologyIri(),
                'cellSource'      => $bs->getCellSource() !== null ? $base . 'cellsource/' . $bs->getCellSource()->getId()->toRfc4122() : null,
                'validationStatus'=> $bs->getValidationStatus(),
            ], static fn($v) => $v !== null && $v !== '');
        }

        /** @var Exposure $ex */
        foreach ($data['exposures'] as $ex) {
            $node = ['@id' => $base . 'exposure/' . $ex->getId()->toRfc4122(), '@type' => 'Exposure', 'label' => $ex->getLabel(), 'testArticle' => $ex->getTestArticleOntologyIri() ?? $ex->getTestArticle()];
            if ($ex->getConcentrationValue() !== null) { $node['nam:concentrationValue'] = $ex->getConcentrationValue(); }
            if ($ex->getConcentrationUnit() !== null) { $node['nam:concentrationUnit'] = $ex->getConcentrationUnit(); }
            if ($ex->getTimepointValue() !== null) { $node['timepointValue'] = $ex->getTimepointValue(); }
            if ($ex->getTimepointUnit() !== null) { $node['timepointUnit'] = $ex->getTimepointUnit(); }
            $graph[] = array_filter($node, static fn($v) => $v !== null && $v !== '');
        }

        /** @var Assay $assay */
        foreach ($data['assays'] as $assay) {
            $graph[] = array_filter([
                '@id'   => $base . 'assay/' . $assay->getId()->toRfc4122(),
                '@type' => 'Assay',
                'label' => $assay->getLabel(),
                'nam:assayType' => $assay->getAssayType(),
                'nam:technology' => $assay->getTechnologyOntologyIri(),
            ], static fn($v) => $v !== null && $v !== '');
        }

        /** @var Sample $sample */
        foreach ($data['samples'] as $sample) {
            $graph[] = array_filter([
                '@id'   => $base . 'sample/' . $sample->getId()->toRfc4122(),
                '@type' => 'Sample',
                'label' => $sample->getSampleCode(),
            ], static fn($v) => $v !== null && $v !== '');
        }

        /** @var RawDataFile $raw */
        foreach ($data['rawFiles'] as $raw) {
            $graph[] = array_filter([
                '@id'   => $base . 'rawfile/' . $raw->getId()->toRfc4122(),
                '@type' => 'RawDataFile',
                'label' => $raw->getFileName(),
                'nam:checksum' => $raw->getChecksum(),
            ], static fn($v) => $v !== null && $v !== '');
        }

        /** @var ProvenanceActivity $act */
        foreach ($data['activities'] as $act) {
            $node = [
                '@id'   => $base . 'activity/' . $act->getId()->toRfc4122(),
                '@type' => 'ProvenanceActivity',
                'label' => $act->getLabel(),
            ];
            if ($act->getSoftwareName() !== null) { $node['nam:softwareName'] = $act->getSoftwareName(); }
            if ($act->getScriptReference() !== null) { $node['nam:scriptReference'] = $act->getScriptReference(); }
            if ($act->getEndedAt() !== null) { $node['generatedAtTime'] = $act->getEndedAt()->format(\DateTimeInterface::ATOM); }
            $graph[] = array_filter($node, static fn($v) => $v !== null && $v !== '');
        }

        /** @var EndpointMeasurement $m */
        foreach ($data['measurements'] as $m) {
            $node = [
                '@id'      => $base . 'measurement/' . $m->getId()->toRfc4122(),
                '@type'    => 'EndpointMeasurement',
                'label'    => $m->getEndpointLabel(),
                'endpoint' => $m->getEndpointOntologyIri() ?? ('nam:endpoint/' . $m->getEndpointId()),
            ];
            if ($m->getValue() !== null) { $node['value'] = $m->getValue(); }
            if ($m->getUnit() !== null) { $node['unit'] = $m->getUnitOntologyIri() ?? $m->getUnit(); }
            if ($m->getTimepointValue() !== null) { $node['timepointValue'] = $m->getTimepointValue(); }
            if ($m->getTimepointUnit() !== null) { $node['timepointUnit'] = $m->getTimepointUnit(); }
            if ($m->getSample() !== null) { $node['nam:sample'] = $base . 'sample/' . $m->getSample()->getId()->toRfc4122(); }
            if ($m->getAssay() !== null) { $node['nam:assay'] = $base . 'assay/' . $m->getAssay()->getId()->toRfc4122(); }
            if ($m->getExposure() !== null) { $node['nam:exposure'] = $base . 'exposure/' . $m->getExposure()->getId()->toRfc4122(); }
            if ($m->getRawDataFile() !== null) { $node['wasDerivedFrom'] = $base . 'rawfile/' . $m->getRawDataFile()->getId()->toRfc4122(); }
            if ($m->getAnalysisActivity() !== null) { $node['wasGeneratedBy'] = $base . 'activity/' . $m->getAnalysisActivity()->getId()->toRfc4122(); }
            $graph[] = array_filter($node, static fn($v) => $v !== null && $v !== '');
        }

        /** @var EvidenceItem $item */
        foreach ($data['evidence'] as $item) {
            $graph[] = array_filter([
                '@id'   => $base . 'evidence/' . $item->getEvidenceId(),
                '@type' => 'ValidationEvidence',
                'label' => $item->getEvidenceId(),
                'nam:domain' => $item->getDomain(),
                'nam:status' => $item->getStatus(),
            ], static fn($v) => $v !== null && $v !== '');
        }

        /** @var ClaimNode $claim */
        foreach ($data['claims'] as $claim) {
            $node = [
                '@id'          => $base . 'claim/' . $claim->getClaimId(),
                '@type'        => 'EvidenceClaim',
                'label'        => $claim->getClaimId(),
                'description'  => $claim->getClaimText(),
                'reviewStatus' => $claim->getReviewStatus(),
                'nam:confidence' => $claim->getConfidence(),
            ];
            $support = [];
            foreach ($claim->getSupportingEvidence() as $ev) {
                $support[] = $base . 'evidence/' . $ev;
            }
            if (count($support) > 0) { $node['supportedBy'] = $support; }
            $graph[] = array_filter($node, static fn($v) => $v !== null && $v !== '' && $v !== []);
        }

        /** @var OntologyMapping $map */
        foreach ($data['ontologyMappings'] as $map) {
            $graph[] = array_filter([
                '@id'    => $base . 'mapping/' . $map->getId()->toRfc4122(),
                '@type'  => 'OntologyMapping',
                'label'  => $map->getSourceValue(),
                'nam:mappingStatus' => $map->getMappingStatus(),
                'nam:mappedTerm' => $map->getOntologyTerm()?->getIri() ?? $map->getOntologyTerm()?->getCurie(),
            ], static fn($v) => $v !== null && $v !== '');
        }

        return [
            '@context' => self::CONTEXT_URL,
            '@id'      => $base . 'package',
            '@type'    => 'EvidencePackage',
            'nam:schemaVersion' => self::SCHEMA_VERSION,
            '@graph'   => $graph,
        ];
    }

    /**
     * @return array<string, list<array<string,mixed>>>
     */
    public function toTables(Project $project): array
    {
        $data = $this->collect($project);
        $tables = [
            'endpoint_measurements' => [],
            'samples'               => [],
            'exposures'             => [],
            'assays'                => [],
            'validation_evidence'   => [],
        ];

        /** @var EndpointMeasurement $m */
        foreach ($data['measurements'] as $m) {
            $tables['endpoint_measurements'][] = [
                'measurement_id'   => $m->getId()->toRfc4122(),
                'endpoint_id'      => $m->getEndpointId(),
                'endpoint_label'   => $m->getEndpointLabel(),
                'endpoint_iri'     => $m->getEndpointOntologyIri(),
                'value'            => $m->getValue(),
                'value_raw'        => $m->getValueRaw(),
                'unit'             => $m->getUnit(),
                'unit_iri'         => $m->getUnitOntologyIri(),
                'timepoint_value'  => $m->getTimepointValue(),
                'timepoint_unit'   => $m->getTimepointUnit(),
                'replicate_id'     => $m->getReplicateId(),
                'batch_id'         => $m->getBatchId(),
                'qc_status'        => $m->getQcStatus(),
                'exclusion_status' => $m->getExclusionStatus(),
                'sample_code'      => $m->getSample()?->getSampleCode(),
                'assay'            => $m->getAssay()?->getLabel(),
                'exposure'         => $m->getExposure()?->getTestArticle(),
                'raw_file'         => $m->getRawDataFile()?->getFileName(),
            ];
        }
        /** @var Sample $s */
        foreach ($data['samples'] as $s) {
            $tables['samples'][] = [
                'sample_id'   => $s->getId()->toRfc4122(),
                'sample_code' => $s->getSampleCode(),
                'batch_id'    => $s->getBatchId(),
                'replicate_id'=> $s->getReplicateId(),
                'label'       => $s->getLabel(),
            ];
        }
        /** @var Exposure $e */
        foreach ($data['exposures'] as $e) {
            $tables['exposures'][] = [
                'exposure_id'        => $e->getId()->toRfc4122(),
                'test_article'       => $e->getTestArticle(),
                'test_article_iri'   => $e->getTestArticleOntologyIri(),
                'concentration_value'=> $e->getConcentrationValue(),
                'concentration_unit' => $e->getConcentrationUnit(),
                'timepoint_value'    => $e->getTimepointValue(),
                'timepoint_unit'     => $e->getTimepointUnit(),
                'vehicle'            => $e->getVehicle(),
            ];
        }
        /** @var Assay $a */
        foreach ($data['assays'] as $a) {
            $tables['assays'][] = [
                'assay_id'       => $a->getId()->toRfc4122(),
                'label'          => $a->getLabel(),
                'assay_type'     => $a->getAssayType(),
                'readout'        => $a->getReadout(),
                'technology_iri' => $a->getTechnologyOntologyIri(),
            ];
        }
        /** @var EvidenceItem $ev */
        foreach ($data['evidence'] as $ev) {
            $tables['validation_evidence'][] = [
                'evidence_id' => $ev->getEvidenceId(),
                'domain'      => $ev->getDomain(),
                'question'    => $ev->getQuestion(),
                'status'      => $ev->getStatus(),
                'notes'       => $ev->getNotes(),
            ];
        }

        return $tables;
    }
}
