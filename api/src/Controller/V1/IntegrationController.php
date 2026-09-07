<?php

declare(strict_types=1);

namespace App\Controller\V1;

use App\Entity\ContextOfUseCard;
use App\Entity\EvidenceItem;
use App\Entity\NamCore\EvidenceAssessment;
use App\Entity\NamCore\NAMMethod;
use App\Entity\NamCore\SourceProject;
use App\Entity\NAMStudy;
use App\Entity\Project;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/** Source-agnostic normalized ingestion endpoint for external NAM adapters. */
#[Route('/api/v1/integrations')]
final class IntegrationController extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    #[Route('/normalized', name: 'api_v1_integrations_normalized', methods: ['POST'])]
    public function importNormalized(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->json(['error' => 'Invalid JSON body'], Response::HTTP_BAD_REQUEST);
        }

        $projectData = $data['project'] ?? null;
        $methodData = $data['method'] ?? null;
        $couData = $data['contexts_of_use'] ?? null;
        if (!is_array($projectData) || trim((string) ($projectData['name'] ?? '')) === '') {
            return $this->json(['error' => 'project.name is required'], Response::HTTP_BAD_REQUEST);
        }
        if (!is_array($methodData) || trim((string) ($methodData['method_id'] ?? '')) === '' || trim((string) ($methodData['name'] ?? '')) === '' || trim((string) ($methodData['method_type'] ?? '')) === '') {
            return $this->json(['error' => 'method.method_id, method.name and method.method_type are required'], Response::HTTP_BAD_REQUEST);
        }
        if (!is_array($couData) || $couData === []) {
            return $this->json(['error' => 'contexts_of_use must contain at least one CoU'], Response::HTTP_BAD_REQUEST);
        }

        $project = (new Project())
            ->setName((string) $projectData['name'])
            ->setDescription($this->nullableString($projectData['description'] ?? null))
            ->setProjectType((string) ($projectData['project_type'] ?? 'other'))
            ->setDrugName($this->nullableString($projectData['drug_name'] ?? null))
            ->setSponsor($this->nullableString($projectData['sponsor'] ?? null))
            ->setSourceName($this->nullableString($projectData['source_name'] ?? null))
            ->setExternalId($this->nullableString($projectData['external_id'] ?? null))
            ->setSourceUrl($this->nullableString($projectData['source_url'] ?? null));
        $this->em->persist($project);

        $sourceProject = null;
        if (isset($data['source_project']) && is_array($data['source_project'])) {
            $source = $data['source_project'];
            $sourceProject = (new SourceProject())
                ->setProject($project)
                ->setLabel((string) ($source['label'] ?? $project->getSourceName() ?? $project->getName()))
                ->setDescription($this->nullableString($source['description'] ?? null))
                ->setSourceType((string) ($source['source_type'] ?? 'other'))
                ->setExternalId($this->nullableString($source['external_id'] ?? $project->getExternalId()))
                ->setOrganisation($this->nullableString($source['organisation'] ?? null))
                ->setSourceUrl($this->nullableString($source['source_url'] ?? $project->getSourceUrl()))
                ->setSourceVersion($this->nullableString($source['source_version'] ?? null))
                ->setExtensions($this->arrayValue($source['extensions'] ?? []));
            if (isset($source['source_updated_at']) && is_string($source['source_updated_at'])) {
                try { $sourceProject->setSourceUpdatedAt(new \DateTimeImmutable($source['source_updated_at'])); }
                catch (\Throwable) { return $this->json(['error' => 'source_project.source_updated_at is not a valid date'], Response::HTTP_BAD_REQUEST); }
            }
            $this->em->persist($sourceProject);
        }

        $method = (new NAMMethod())
            ->setProject($project)->setSourceProject($sourceProject)
            ->setMethodId((string) $methodData['method_id'])->setLabel((string) $methodData['name'])
            ->setDescription($this->nullableString($methodData['description'] ?? null))
            ->setMethodType((string) $methodData['method_type'])
            ->setDeveloper($this->nullableString($methodData['developer'] ?? null))
            ->setMethodVersion($this->nullableString($methodData['method_version'] ?? null))
            ->setTechnicalDescription($this->nullableString($methodData['technical_description'] ?? null))
            ->setMaturityStatus((string) ($methodData['maturity_status'] ?? 'unknown'))
            ->setOntologyIri($this->nullableString($methodData['ontology_iri'] ?? null))
            ->setExternalIdentifiers($this->arrayValue($methodData['external_identifiers'] ?? []))
            ->setExtensions($this->arrayValue($methodData['extensions'] ?? []));
        $this->em->persist($method);

        /** @var array<string,ContextOfUseCard> $contextsById */
        $contextsById = [];
        foreach ($couData as $row) {
            if (!is_array($row)) return $this->json(['error' => 'Each contexts_of_use item must be an object'], Response::HTTP_BAD_REQUEST);
            $couId = trim((string) ($row['cou_id'] ?? ''));
            $question = trim((string) ($row['regulatory_question'] ?? ''));
            if ($couId === '' || $question === '') return $this->json(['error' => 'Each CoU requires cou_id and regulatory_question'], Response::HTTP_BAD_REQUEST);
            $cou = (new ContextOfUseCard())
                ->setCouId($couId)->setProject($project)->setNamMethod($method)
                ->setNamType((string) ($row['nam_type'] ?? $method->getMethodType()))
                ->setRegulatoryQuestion($question)->setDrugDevelopmentStage((string) ($row['development_stage'] ?? ''))
                ->setIntendedUse((string) ($row['intended_use'] ?? ''))->setDecisionSupported((string) ($row['decision_supported'] ?? ''))
                ->setBiologicalDomain((string) ($row['biological_domain'] ?? ''))->setEndpointClass((string) ($row['endpoint_class'] ?? ''))
                ->setPopulationRelevance($this->nullableString($row['population_relevance'] ?? null))
                ->setTestArticleScope($this->nullableString($row['test_article_scope'] ?? null))
                ->setApplicabilityDomain($this->arrayValue($row['applicability_domain'] ?? []))
                ->setRegulatoryAuthority($this->nullableString($row['regulatory_authority'] ?? null))
                ->setSourceText($this->nullableString($row['source_text'] ?? null))
                ->setSourceReference($this->nullableString($row['source_reference'] ?? null))
                ->setLimitations($this->arrayValue($row['limitations'] ?? []))->setAcceptanceCriteria($this->arrayValue($row['acceptance_criteria'] ?? []))
                ->setRegulatoryConfidenceLevel((string) ($row['support_level'] ?? 'exploratory'))
                ->setReviewStatus((string) ($row['review_status'] ?? 'draft'))->setVersion((string) ($row['version'] ?? '1.0'));
            $this->em->persist($cou); $contextsById[$couId] = $cou;
        }

        /** @var array<string,NAMStudy> $studiesById */
        $studiesById = [];
        foreach ($this->arrayValue($data['studies'] ?? []) as $row) {
            if (!is_array($row)) continue;
            $studyId = trim((string) ($row['study_id'] ?? '')); $couId = trim((string) ($row['cou_id'] ?? ''));
            if ($studyId === '' || $couId === '' || !isset($contextsById[$couId])) return $this->json(['error' => 'Each study requires study_id and a cou_id present in contexts_of_use'], Response::HTTP_BAD_REQUEST);
            $study = (new NAMStudy())->setStudyId($studyId)->setProject($project)->setContextOfUse($contextsById[$couId])->setNamMethod($method)
                ->setTitle((string) ($row['title'] ?? $studyId))->setModelSystem($this->arrayValue($row['model_system'] ?? []))
                ->setExperimentalDesign($this->arrayValue($row['experimental_design'] ?? []))->setAssayMetadata($this->arrayValue($row['assay_metadata'] ?? []))
                ->setDataOutputs($this->arrayValue($row['data_outputs'] ?? []))->setProvenance($this->arrayValue($row['provenance'] ?? []));
            $this->em->persist($study); $studiesById[$studyId] = $study;
        }

        $evidenceCount = 0;
        foreach ($this->arrayValue($data['evidence'] ?? []) as $row) {
            if (!is_array($row)) continue;
            $evidenceId = trim((string) ($row['evidence_id'] ?? '')); $studyId = trim((string) ($row['study_id'] ?? ''));
            if ($evidenceId === '' || !isset($studiesById[$studyId])) return $this->json(['error' => 'Each evidence item requires evidence_id and a study_id present in studies'], Response::HTTP_BAD_REQUEST);
            $item = (new EvidenceItem())->setEvidenceId($evidenceId)->setStudy($studiesById[$studyId])
                ->setDomain((string) ($row['domain'] ?? ''))->setQuestion((string) ($row['question'] ?? ''))->setEvidenceType((string) ($row['evidence_type'] ?? ''))
                ->setStatus((string) ($row['status'] ?? 'not_applicable'))->setNotes($this->nullableString($row['notes'] ?? null))
                ->setSupportingData($this->nullableString($row['supporting_data'] ?? null))->setMetricValue($this->nullableString($row['metric_value'] ?? null))
                ->setThreshold($this->nullableString($row['threshold'] ?? null))->setPassFail($this->nullableString($row['pass_fail'] ?? null));
            $this->em->persist($item); $evidenceCount++;
        }

        $assessmentCount = 0;
        foreach ($this->arrayValue($data['assessments'] ?? []) as $row) {
            if (!is_array($row)) continue;
            $couId = $this->nullableString($row['cou_id'] ?? null);
            if ($couId !== null && !isset($contextsById[$couId])) return $this->json(['error' => "Assessment references unknown cou_id: $couId"], Response::HTTP_BAD_REQUEST);
            $type = trim((string) ($row['assessment_type'] ?? '')); $assessor = trim((string) ($row['assessor_organization'] ?? ''));
            if ($type === '' || $assessor === '') return $this->json(['error' => 'Each assessment requires assessment_type and assessor_organization'], Response::HTTP_BAD_REQUEST);
            $assessment = (new EvidenceAssessment())->setProject($project)->setNamMethod($method)->setContextOfUse($couId !== null ? $contextsById[$couId] : null)
                ->setSourceProject($sourceProject)->setLabel((string) ($row['label'] ?? "$type — $assessor"))->setDescription($this->nullableString($row['description'] ?? null))
                ->setAssessmentType($type)->setAssessorOrganization($assessor)->setAuthority($this->nullableString($row['authority'] ?? null))
                ->setStatus((string) ($row['status'] ?? 'under_review'))->setConclusion($this->nullableString($row['conclusion'] ?? null))
                ->setSourceUrl($this->nullableString($row['source_url'] ?? null))->setEvidenceBasis($this->arrayValue($row['evidence_basis'] ?? []))->setConditions($this->arrayValue($row['conditions'] ?? []));
            if (isset($row['assessed_at']) && is_string($row['assessed_at'])) {
                try { $assessment->setAssessedAt(new \DateTimeImmutable($row['assessed_at'])); }
                catch (\Throwable) { return $this->json(['error' => 'assessment.assessed_at is not a valid date'], Response::HTTP_BAD_REQUEST); }
            }
            $this->em->persist($assessment); $assessmentCount++;
        }

        try { $this->em->flush(); }
        catch (UniqueConstraintViolationException $e) { return $this->json(['error' => 'An imported business identifier already exists. Use distinct project-scoped IDs or update the existing record.', 'detail' => $e->getMessage()], Response::HTTP_CONFLICT); }

        return $this->json([
            'status' => 'imported', 'project_id' => (string) $project->getId(),
            'source_project_id' => $sourceProject !== null ? (string) $sourceProject->getId() : null,
            'method_id' => $method->getMethodId(), 'method_entity_id' => (string) $method->getId(),
            'contexts_of_use' => array_keys($contextsById), 'study_count' => count($studiesById),
            'evidence_count' => $evidenceCount, 'assessment_count' => $assessmentCount,
        ], Response::HTTP_CREATED);
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) return null;
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    /** @return array<mixed> */
    private function arrayValue(mixed $value): array { return is_array($value) ? $value : []; }
}
