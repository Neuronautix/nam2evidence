<?php

declare(strict_types=1);

namespace App\Controller\V1;

use App\Entity\ContextOfUseCard;
use App\Entity\EvidenceItem;
use App\Entity\NamCore\GuidanceProfile;
use App\Entity\NamCore\GuidanceRequirement;
use App\Entity\NamCore\RequirementAssessment;
use App\Entity\NamCore\RequirementEvidenceLink;
use App\Entity\Project;
use App\Service\NamCore\AuditLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Regulatory-evidence reference and assessment API.
 *
 * Guidance profiles/requirements are intentionally read-only here: curated
 * source changes must create a new profile version. Project-scoped assessments
 * are mutable and always audited.
 */
final class RegulatoryEvidenceController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ValidatorInterface $validator,
        private readonly AuditLogger $audit,
    ) {}

    #[Route('/api/v1/guidance-profiles', name: 'v1_guidance_profiles_list', methods: ['GET'])]
    public function listProfiles(Request $request): JsonResponse
    {
        /** @var GuidanceProfile[] $profiles */
        $profiles = $this->em->getRepository(GuidanceProfile::class)->findAll();

        $authority = strtolower(trim((string) $request->query->get('authority', '')));
        $status = strtolower(trim((string) $request->query->get('status', '')));
        $jurisdiction = strtolower(trim((string) $request->query->get('jurisdiction', '')));

        $profiles = array_values(array_filter($profiles, static function (GuidanceProfile $p) use ($authority, $status, $jurisdiction): bool {
            if ($authority !== '' && !str_contains(strtolower($p->getAuthority()), $authority)) return false;
            if ($status !== '' && strtolower($p->getStatus()) !== $status) return false;
            if ($jurisdiction !== '' && !str_contains(strtolower($p->getJurisdiction()), $jurisdiction)) return false;
            return true;
        }));

        usort($profiles, static fn(GuidanceProfile $a, GuidanceProfile $b): int =>
            [$a->getAuthority(), $a->getTitle(), $a->getVersion()] <=> [$b->getAuthority(), $b->getTitle(), $b->getVersion()]
        );

        return $this->json([
            'count' => count($profiles),
            'profiles' => array_map($this->serializeProfile(...), $profiles),
            'interpretation_note' => 'Guidance profiles are source-cited reference interpretations. They do not encode regulatory acceptance or automatic submission readiness.',
        ]);
    }

    #[Route('/api/v1/guidance-profiles/{id}', name: 'v1_guidance_profile_get', methods: ['GET'])]
    public function getProfile(string $id): JsonResponse
    {
        $profile = $this->findByUlid(GuidanceProfile::class, $id);
        if (!$profile instanceof GuidanceProfile) {
            return $this->json(['error' => 'Guidance profile not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->serializeProfile($profile));
    }

    #[Route('/api/v1/guidance-profiles/{id}/requirements', name: 'v1_guidance_profile_requirements', methods: ['GET'])]
    public function listRequirements(string $id): JsonResponse
    {
        $profile = $this->findByUlid(GuidanceProfile::class, $id);
        if (!$profile instanceof GuidanceProfile) {
            return $this->json(['error' => 'Guidance profile not found'], Response::HTTP_NOT_FOUND);
        }

        /** @var GuidanceRequirement[] $requirements */
        $requirements = $this->em->getRepository(GuidanceRequirement::class)->findBy(['profile' => $profile], ['requirementKey' => 'ASC']);

        return $this->json([
            'profile' => $this->serializeProfile($profile),
            'count' => count($requirements),
            'requirements' => array_map($this->serializeRequirement(...), $requirements),
        ]);
    }

    #[Route('/api/v1/projects/{id}/requirement-assessments', name: 'v1_requirement_assessments_list', methods: ['GET'])]
    public function listAssessments(string $id, Request $request): JsonResponse
    {
        $project = $this->findProject($id);
        if ($project === null) {
            return $this->json(['error' => 'Project not found'], Response::HTTP_NOT_FOUND);
        }

        /** @var RequirementAssessment[] $assessments */
        $assessments = $this->em->getRepository(RequirementAssessment::class)->findBy(['project' => $project], ['createdAt' => 'ASC']);

        $couId = trim((string) $request->query->get('context_of_use_id', ''));
        $profileId = trim((string) $request->query->get('profile_id', ''));

        $assessments = array_values(array_filter($assessments, static function (RequirementAssessment $a) use ($couId, $profileId): bool {
            if ($couId !== '' && $a->getContextOfUse()->getId()->toRfc4122() !== self::normaliseUlid($couId)) return false;
            if ($profileId !== '' && $a->getRequirement()->getProfile()->getId()->toRfc4122() !== self::normaliseUlid($profileId)) return false;
            return true;
        }));

        $summary = [
            RequirementAssessment::STATUS_SUPPORTED => 0,
            RequirementAssessment::STATUS_PARTIAL => 0,
            RequirementAssessment::STATUS_MISSING => 0,
            RequirementAssessment::STATUS_NOT_APPLICABLE => 0,
            RequirementAssessment::STATUS_REQUIRES_HUMAN => 0,
        ];
        foreach ($assessments as $assessment) {
            if (array_key_exists($assessment->getStatus(), $summary)) {
                $summary[$assessment->getStatus()]++;
            }
        }

        return $this->json([
            'count' => count($assessments),
            'summary' => $summary,
            'assessments' => array_map($this->serializeAssessment(...), $assessments),
            'interpretation_note' => 'Assessment status describes evidence coverage for a specific Context of Use and profile version; it is not a regulatory-acceptance determination.',
        ]);
    }

    #[Route('/api/v1/projects/{id}/requirement-assessments', name: 'v1_requirement_assessments_create', methods: ['POST'])]
    public function createAssessment(string $id, Request $request): JsonResponse
    {
        $project = $this->findProject($id);
        if ($project === null) {
            return $this->json(['error' => 'Project not found'], Response::HTTP_NOT_FOUND);
        }

        $body = $this->jsonBody($request);
        $cou = $this->findByUlid(ContextOfUseCard::class, (string) ($body['context_of_use_id'] ?? ''));
        $requirement = $this->findByUlid(GuidanceRequirement::class, (string) ($body['requirement_id'] ?? ''));

        if (!$cou instanceof ContextOfUseCard || !$requirement instanceof GuidanceRequirement) {
            return $this->json(['error' => 'context_of_use_id and requirement_id must reference existing records.'], Response::HTTP_BAD_REQUEST);
        }

        if ($cou->getProject()->getId()->toRfc4122() !== $project->getId()->toRfc4122()) {
            return $this->json(['error' => 'Context of Use does not belong to this project.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $assessment = (new RequirementAssessment())
            ->setProject($project)
            ->setContextOfUse($cou)
            ->setRequirement($requirement)
            ->setStatus((string) ($body['status'] ?? RequirementAssessment::STATUS_REQUIRES_HUMAN))
            ->setAssessmentOrigin((string) ($body['assessment_origin'] ?? RequirementAssessment::ORIGIN_HUMAN))
            ->setReviewStatus((string) ($body['review_status'] ?? RequirementAssessment::REVIEW_HUMAN_REQUIRED))
            ->setRationale(isset($body['rationale']) ? (string) $body['rationale'] : null)
            ->setReviewedBy(isset($body['reviewed_by']) ? trim((string) $body['reviewed_by']) : null)
            ->setReviewerComment(isset($body['reviewer_comment']) ? (string) $body['reviewer_comment'] : null);

        if ($assessment->getReviewStatus() === RequirementAssessment::REVIEW_HUMAN_REVIEWED) {
            $assessment->setReviewedAt(new \DateTimeImmutable());
        }

        $violations = $this->validator->validate($assessment);
        if (count($violations) > 0) {
            return $this->validationError($violations);
        }

        $links = [];
        foreach (($body['evidence_links'] ?? []) as $linkData) {
            if (!is_array($linkData)) {
                return $this->json(['error' => 'Each evidence_links item must be an object.'], Response::HTTP_BAD_REQUEST);
            }
            $evidence = $this->findByUlid(EvidenceItem::class, (string) ($linkData['evidence_item_id'] ?? ''));
            if (!$evidence instanceof EvidenceItem) {
                return $this->json(['error' => 'Evidence item not found.'], Response::HTTP_BAD_REQUEST);
            }

            $link = (new RequirementEvidenceLink())
                ->setAssessment($assessment)
                ->setEvidenceItem($evidence)
                ->setRelationship((string) ($linkData['relationship'] ?? RequirementEvidenceLink::REL_SUPPORTS))
                ->setProvenance(is_array($linkData['provenance'] ?? null) ? $linkData['provenance'] : []);

            $linkViolations = $this->validator->validate($link);
            if (count($linkViolations) > 0) {
                return $this->validationError($linkViolations);
            }
            $links[] = $link;
        }

        $this->em->persist($assessment);
        foreach ($links as $link) $this->em->persist($link);
        $this->em->flush();

        $serialized = $this->serializeAssessment($assessment);
        $this->audit->log(
            $project,
            'RequirementAssessment',
            $assessment->getId()->toRfc4122(),
            'create',
            null,
            $serialized,
            isset($body['reason']) ? (string) $body['reason'] : null,
            isset($body['created_by']) ? (string) $body['created_by'] : 'system',
        );

        return $this->json($serialized, Response::HTTP_CREATED);
    }

    #[Route('/api/v1/projects/{id}/requirement-assessments/{assessmentId}', name: 'v1_requirement_assessments_update', methods: ['PATCH'])]
    public function updateAssessment(string $id, string $assessmentId, Request $request): JsonResponse
    {
        $project = $this->findProject($id);
        if ($project === null) {
            return $this->json(['error' => 'Project not found'], Response::HTTP_NOT_FOUND);
        }

        $assessment = $this->findByUlid(RequirementAssessment::class, $assessmentId);
        if (!$assessment instanceof RequirementAssessment
            || $assessment->getProject()->getId()->toRfc4122() !== $project->getId()->toRfc4122()) {
            return $this->json(['error' => 'Requirement assessment not found in this project'], Response::HTTP_NOT_FOUND);
        }

        $body = $this->jsonBody($request);
        $before = $this->serializeAssessment($assessment);

        if (array_key_exists('status', $body)) $assessment->setStatus((string) $body['status']);
        if (array_key_exists('rationale', $body)) $assessment->setRationale($body['rationale'] === null ? null : (string) $body['rationale']);
        if (array_key_exists('review_status', $body)) $assessment->setReviewStatus((string) $body['review_status']);
        if (array_key_exists('reviewed_by', $body)) $assessment->setReviewedBy($body['reviewed_by'] === null ? null : trim((string) $body['reviewed_by']));
        if (array_key_exists('reviewer_comment', $body)) $assessment->setReviewerComment($body['reviewer_comment'] === null ? null : (string) $body['reviewer_comment']);

        if ($assessment->getReviewStatus() === RequirementAssessment::REVIEW_HUMAN_REVIEWED) {
            $assessment->setReviewedAt(new \DateTimeImmutable());
        } elseif (array_key_exists('review_status', $body)) {
            $assessment->setReviewedAt(null);
        }

        $violations = $this->validator->validate($assessment);
        if (count($violations) > 0) {
            return $this->validationError($violations);
        }

        $this->em->flush();
        $after = $this->serializeAssessment($assessment);
        $actor = $assessment->getReviewedBy() ?? (isset($body['updated_by']) ? (string) $body['updated_by'] : 'system');

        $this->audit->log(
            $project,
            'RequirementAssessment',
            $assessment->getId()->toRfc4122(),
            'update',
            $before,
            $after,
            isset($body['reason']) ? (string) $body['reason'] : null,
            $actor,
        );

        return $this->json($after);
    }

    /** @return array<string,mixed> */
    private function serializeProfile(GuidanceProfile $p): array
    {
        return [
            'id' => $p->getId()->toRfc4122(),
            'profile_key' => $p->getProfileKey(),
            'authority' => $p->getAuthority(),
            'title' => $p->getTitle(),
            'canonical_source_url' => $p->getCanonicalSourceUrl(),
            'publication_date' => $p->getPublicationDate()?->format('Y-m-d'),
            'effective_date' => $p->getEffectiveDate()?->format('Y-m-d'),
            'status' => $p->getStatus(),
            'version' => $p->getVersion(),
            'source_version' => $p->getSourceVersion(),
            'jurisdiction' => $p->getJurisdiction(),
            'source_hash' => $p->getSourceHash(),
            'retrieved_at' => $p->getRetrievedAt()->format(\DateTimeInterface::ATOM),
            'source_metadata' => $p->getSourceMetadata(),
        ];
    }

    /** @return array<string,mixed> */
    private function serializeRequirement(GuidanceRequirement $r): array
    {
        return [
            'id' => $r->getId()->toRfc4122(),
            'requirement_key' => $r->getRequirementKey(),
            'profile_id' => $r->getProfile()->getId()->toRfc4122(),
            'profile_key' => $r->getProfile()->getProfileKey(),
            'profile_version' => $r->getProfile()->getVersion(),
            'title' => $r->getTitle(),
            'requirement_text' => $r->getRequirementText(),
            'evidence_domain' => $r->getEvidenceDomain(),
            'applicability_rules' => $r->getApplicabilityRules(),
            'importance' => $r->getImportance(),
            'source_locator' => $r->getSourceLocator(),
            'source_url' => $r->getProfile()->getCanonicalSourceUrl(),
            'interpretation_note' => $r->getInterpretationNote(),
        ];
    }

    /** @return array<string,mixed> */
    private function serializeAssessment(RequirementAssessment $a): array
    {
        /** @var RequirementEvidenceLink[] $links */
        $links = $this->em->getRepository(RequirementEvidenceLink::class)->findBy(['assessment' => $a], ['createdAt' => 'ASC']);

        return [
            'id' => $a->getId()->toRfc4122(),
            'project_id' => $a->getProject()->getId()->toRfc4122(),
            'context_of_use_id' => $a->getContextOfUse()->getId()->toRfc4122(),
            'context_of_use_key' => $a->getContextOfUse()->getCouId(),
            'requirement' => $this->serializeRequirement($a->getRequirement()),
            'status' => $a->getStatus(),
            'rationale' => $a->getRationale(),
            'assessment_origin' => $a->getAssessmentOrigin(),
            'review_status' => $a->getReviewStatus(),
            'reviewed_by' => $a->getReviewedBy(),
            'reviewed_at' => $a->getReviewedAt()?->format(\DateTimeInterface::ATOM),
            'reviewer_comment' => $a->getReviewerComment(),
            'evidence_links' => array_map($this->serializeEvidenceLink(...), $links),
            'created_at' => $a->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'updated_at' => $a->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }

    /** @return array<string,mixed> */
    private function serializeEvidenceLink(RequirementEvidenceLink $link): array
    {
        $e = $link->getEvidenceItem();
        return [
            'id' => $link->getId()->toRfc4122(),
            'relationship' => $link->getRelationship(),
            'provenance' => $link->getProvenance(),
            'evidence_item' => [
                'id' => $e->getId()->toRfc4122(),
                'evidence_id' => $e->getEvidenceId(),
                'domain' => $e->getDomain(),
                'status' => $e->getStatus(),
                'question' => $e->getQuestion(),
            ],
        ];
    }

    /** @return array<string,mixed> */
    private function jsonBody(Request $request): array
    {
        $content = (string) $request->getContent();
        if ($content === '') return [];
        try {
            $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
            return is_array($decoded) ? $decoded : [];
        } catch (\JsonException) {
            return [];
        }
    }

    private function findProject(string $id): ?Project
    {
        $found = $this->findByUlid(Project::class, $id);
        return $found instanceof Project ? $found : null;
    }

    private function findByUlid(string $class, string $id): ?object
    {
        try {
            return $this->em->find($class, Ulid::fromString($id)->toRfc4122());
        } catch (\Throwable) {
            return null;
        }
    }

    private static function normaliseUlid(string $id): string
    {
        try {
            return Ulid::fromString($id)->toRfc4122();
        } catch (\Throwable) {
            return '';
        }
    }

    private function validationError(ConstraintViolationListInterface $violations): JsonResponse
    {
        $errors = [];
        foreach ($violations as $violation) {
            $errors[] = ['field' => $violation->getPropertyPath(), 'message' => $violation->getMessage()];
        }
        return $this->json(['error' => 'Validation failed', 'violations' => $errors], Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
