<?php

declare(strict_types=1);

namespace App\Controller\V1;

use App\Entity\ClaimNode;
use App\Entity\EvidenceItem;
use App\Entity\NamCore\ExternalStatement;
use App\Entity\NamCore\ExternalStatementLink;
use App\Entity\NamCore\RequirementAssessment;
use App\Entity\Project;
use App\Service\NamCore\AuditLogger;
use App\Service\NamCore\ExternalStatementImporter;
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
 * External statements (TIB Knowledge Loom and similar) as human-linked
 * external evidence.
 *
 *   POST  /api/v1/projects/{id}/external-statements/import          capture (idempotent, fail-closed)
 *   GET   /api/v1/projects/{id}/external-statements                 list + links
 *   PATCH /api/v1/projects/{id}/external-statements/{sid}/verify     a human confirms the capture matches the source
 *   GET   /api/v1/projects/{id}/external-statement-targets          linkable records for the UI
 *   POST  /api/v1/projects/{id}/external-statement-links            create a suggested/human link
 *   PATCH /api/v1/projects/{id}/external-statement-links/{lid}/approve|reject
 *
 * Nothing here changes a claim's review status, a validation-evidence row,
 * the readiness score or the export gate. Statements inform a reviewer; they
 * do not decide for one.
 */
#[Route('/api/v1/projects/{id}')]
final class ExternalStatementController extends AbstractController
{
    public const INTERPRETATION_NOTE = 'External statements are captured, source-attributed evidence from outside this project. They become relevant only through a link that a named reviewer approved, and they never substitute for the project\'s own validation evidence or for human review.';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ValidatorInterface $validator,
        private readonly AuditLogger $audit,
        private readonly ExternalStatementImporter $importer,
    ) {}

    #[Route('/external-statements/import', name: 'v1_external_statements_import', methods: ['POST'])]
    public function import(string $id, Request $request): JsonResponse
    {
        $project = $this->findProject($id);
        if ($project === null) return $this->json(['error' => 'Project not found'], Response::HTTP_NOT_FOUND);

        $body = $this->jsonBody($request);
        if ($body === []) return $this->json(['error' => 'Invalid or empty JSON body'], Response::HTTP_BAD_REQUEST);

        $result = $this->importer->import($project, $body, flush: true);
        if ($result['errors'] !== []) {
            return $this->json(['error' => 'Import rejected', 'contract' => ExternalStatementImporter::CONTRACT, 'errors' => $result['errors']], Response::HTTP_BAD_REQUEST);
        }
        if ($result['conflicts'] !== []) {
            return $this->json(['error' => 'Capture identity conflict: nothing was written', 'contract' => ExternalStatementImporter::CONTRACT, 'conflicts' => $result['conflicts']], Response::HTTP_CONFLICT);
        }

        foreach ($result['created'] as $statement) {
            $this->audit->log($project, 'ExternalStatement', $statement->getId()->toRfc4122(), 'import', null, [
                'source_system' => $statement->getSourceSystem(),
                'statement_uri' => $statement->getStatementUri(),
                'source_version' => $statement->getSourceVersion(),
                'payload_sha256' => $statement->getPayloadSha256(),
                'access_scope' => $statement->getAccessScope(),
                'capture_status' => $statement->getCaptureStatus(),
            ], 'External statement captured; not evidence until a reviewer links it.', (string) ($body['captured_by'] ?? 'system'), flush: false);
        }
        if ($result['created'] !== []) $this->em->flush();

        return $this->json([
            'status' => $result['created'] !== [] ? 'imported' : 'replayed',
            'contract' => ExternalStatementImporter::CONTRACT,
            'created_count' => count($result['created']),
            'replayed_count' => count($result['replayed']),
            'statements' => array_map($this->serializeStatement(...), array_merge($result['created'], $result['replayed'])),
            'interpretation_note' => self::INTERPRETATION_NOTE,
        ], $result['created'] !== [] ? Response::HTTP_CREATED : Response::HTTP_OK);
    }

    #[Route('/external-statements', name: 'v1_external_statements_list', methods: ['GET'])]
    public function list(string $id, Request $request): JsonResponse
    {
        $project = $this->findProject($id);
        if ($project === null) return $this->json(['error' => 'Project not found'], Response::HTTP_NOT_FOUND);

        /** @var ExternalStatement[] $statements */
        $statements = $this->em->getRepository(ExternalStatement::class)->findBy(['project' => $project], ['capturedAt' => 'ASC', 'statementUri' => 'ASC']);
        $scope = trim((string) $request->query->get('access_scope', ''));
        $capture = trim((string) $request->query->get('capture_status', ''));
        $statements = array_values(array_filter($statements, static function (ExternalStatement $s) use ($scope, $capture): bool {
            if ($scope !== '' && $s->getAccessScope() !== $scope) return false;
            if ($capture !== '' && $s->getCaptureStatus() !== $capture) return false;
            return true;
        }));

        $summary = ['total' => count($statements), 'public' => 0, 'restricted' => 0, 'unknown' => 0, 'captured_verified' => 0,
            'links_total' => 0, 'links_suggested' => 0, 'links_approved' => 0, 'links_rejected' => 0, 'links_not_comparable' => 0];
        $serialized = [];
        foreach ($statements as $s) {
            $summary[$s->getAccessScope()]++;
            if ($s->getCaptureStatus() === ExternalStatement::CAPTURE_VERIFIED) $summary['captured_verified']++;
            $row = $this->serializeStatement($s);
            foreach ($row['links'] as $link) {
                $summary['links_total']++;
                $summary['links_' . $link['review_status']]++;
                if ($link['relationship'] === ExternalStatementLink::REL_NOT_COMPARABLE && $link['review_status'] === ExternalStatementLink::REVIEW_APPROVED) $summary['links_not_comparable']++;
            }
            $serialized[] = $row;
        }

        return $this->json(['count' => count($serialized), 'summary' => $summary, 'statements' => $serialized, 'interpretation_note' => self::INTERPRETATION_NOTE]);
    }

    #[Route('/external-statements/{sid}/verify', name: 'v1_external_statements_verify', methods: ['PATCH'])]
    public function verify(string $id, string $sid, Request $request): JsonResponse
    {
        $project = $this->findProject($id);
        if ($project === null) return $this->json(['error' => 'Project not found'], Response::HTTP_NOT_FOUND);
        $statement = $this->findStatement($project, $sid);
        if ($statement === null) return $this->json(['error' => 'External statement not found in this project'], Response::HTTP_NOT_FOUND);

        $body = $this->jsonBody($request);
        $reviewer = trim((string) ($body['reviewed_by'] ?? ''));
        if ($reviewer === '') return $this->json(['error' => 'reviewed_by is required: capture verification is a human decision.'], Response::HTTP_UNPROCESSABLE_ENTITY);

        $before = $this->serializeStatement($statement);
        $statement->setCaptureStatus(ExternalStatement::CAPTURE_VERIFIED)->setVerifiedBy($reviewer)->setVerifiedAt(new \DateTimeImmutable());
        if (array_key_exists('access_scope', $body)) {
            $statement->setAccessScope((string) $body['access_scope']);
        }
        if (array_key_exists('licence', $body)) {
            $statement->setLicence($body['licence'] === null ? null : trim((string) $body['licence']));
        }
        $violations = $this->validator->validate($statement);
        if (count($violations) > 0) return $this->validationError($violations);
        $this->em->flush();

        $after = $this->serializeStatement($statement);
        $this->audit->log($project, 'ExternalStatement', $statement->getId()->toRfc4122(), 'review_gate', $before, $after,
            isset($body['reason']) ? (string) $body['reason'] : 'Reviewer confirmed the captured statement matches its source.', $reviewer);

        return $this->json($after);
    }

    #[Route('/external-statement-targets', name: 'v1_external_statement_targets', methods: ['GET'])]
    public function targets(string $id): JsonResponse
    {
        $project = $this->findProject($id);
        if ($project === null) return $this->json(['error' => 'Project not found'], Response::HTTP_NOT_FOUND);

        $assessments = $this->em->getRepository(RequirementAssessment::class)->findBy(['project' => $project]);
        $claims = $this->em->getRepository(ClaimNode::class)->findBy(['project' => $project]);
        $evidence = [];
        foreach ($this->em->getRepository(\App\Entity\NAMStudy::class)->findBy(['project' => $project]) as $study) {
            foreach ($study->getEvidenceItems() as $item) $evidence[] = $item;
        }

        return $this->json([
            'requirement_assessments' => array_map(static fn(RequirementAssessment $a) => [
                'id' => $a->getId()->toRfc4122(),
                'requirement_key' => $a->getRequirement()->getRequirementKey(),
                'title' => $a->getRequirement()->getTitle(),
                'status' => $a->getStatus(),
                'context_of_use_key' => $a->getContextOfUse()->getCouId(),
            ], $assessments),
            'claims' => array_map(static fn(ClaimNode $c) => [
                'id' => $c->getId()->toRfc4122(),
                'claim_id' => $c->getClaimId(),
                'claim_text' => $c->getClaimText(),
                'confidence' => $c->getConfidence(),
                'review_status' => $c->getReviewStatus(),
            ], $claims),
            'evidence_items' => array_map(static fn(EvidenceItem $e) => [
                'id' => $e->getId()->toRfc4122(),
                'evidence_id' => $e->getEvidenceId(),
                'domain' => $e->getDomain(),
                'question' => $e->getQuestion(),
                'status' => $e->getStatus(),
            ], $evidence),
        ]);
    }

    #[Route('/external-statement-links', name: 'v1_external_statement_links_create', methods: ['POST'])]
    public function createLink(string $id, Request $request): JsonResponse
    {
        $project = $this->findProject($id);
        if ($project === null) return $this->json(['error' => 'Project not found'], Response::HTTP_NOT_FOUND);

        $body = $this->jsonBody($request);
        $statement = $this->findStatement($project, (string) ($body['external_statement_id'] ?? ''));
        if ($statement === null) return $this->json(['error' => 'external_statement_id must reference a statement in this project.'], Response::HTTP_BAD_REQUEST);

        $targetType = trim((string) ($body['target_type'] ?? ''));
        $targetRef = trim((string) ($body['target_id'] ?? ''));
        $link = (new ExternalStatementLink())
            ->setExternalStatement($statement)
            ->setRelationship((string) ($body['relationship'] ?? ExternalStatementLink::REL_CONTEXTUALIZES))
            ->setLinkOrigin((string) ($body['link_origin'] ?? ExternalStatementLink::ORIGIN_HUMAN))
            ->setReviewStatus((string) ($body['review_status'] ?? ExternalStatementLink::REVIEW_SUGGESTED))
            ->setComparabilityNote($this->nullableString($body['comparability_note'] ?? null))
            ->setReviewerNote($this->nullableString($body['reviewer_note'] ?? null))
            ->setProvenance(is_array($body['provenance'] ?? null) ? $body['provenance'] : []);

        $resolved = $this->resolveTarget($project, $link, $targetType, $targetRef);
        if ($resolved !== null) return $this->json(['error' => $resolved], Response::HTTP_BAD_REQUEST);

        if ($link->getReviewStatus() !== ExternalStatementLink::REVIEW_SUGGESTED) {
            $reviewer = trim((string) ($body['reviewed_by'] ?? ''));
            if ($reviewer === '') return $this->json(['error' => 'reviewed_by is required when a link is created already reviewed.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            $link->setReviewedBy($reviewer)->setReviewedAt(new \DateTimeImmutable());
        }

        $violations = $this->validator->validate($link);
        if (count($violations) > 0) return $this->validationError($violations);

        $this->em->persist($link);
        $this->em->flush();

        $serialized = $this->serializeLink($link);
        $this->audit->log($project, 'ExternalStatementLink', $link->getId()->toRfc4122(), 'create', null, $serialized,
            isset($body['reason']) ? (string) $body['reason'] : null, (string) ($body['created_by'] ?? $body['reviewed_by'] ?? 'system'));

        return $this->json($serialized, Response::HTTP_CREATED);
    }

    #[Route('/external-statement-links', name: 'v1_external_statement_links_list', methods: ['GET'])]
    public function listLinks(string $id): JsonResponse
    {
        $project = $this->findProject($id);
        if ($project === null) return $this->json(['error' => 'Project not found'], Response::HTTP_NOT_FOUND);

        $links = $this->linksForProject($project);
        return $this->json(['count' => count($links), 'links' => array_map($this->serializeLink(...), $links), 'interpretation_note' => self::INTERPRETATION_NOTE]);
    }

    #[Route('/external-statement-links/{lid}/approve', name: 'v1_external_statement_links_approve', methods: ['PATCH'])]
    public function approve(string $id, string $lid, Request $request): JsonResponse
    {
        return $this->decide($id, $lid, $request, ExternalStatementLink::REVIEW_APPROVED);
    }

    #[Route('/external-statement-links/{lid}/reject', name: 'v1_external_statement_links_reject', methods: ['PATCH'])]
    public function reject(string $id, string $lid, Request $request): JsonResponse
    {
        return $this->decide($id, $lid, $request, ExternalStatementLink::REVIEW_REJECTED);
    }

    private function decide(string $id, string $lid, Request $request, string $decision): JsonResponse
    {
        $project = $this->findProject($id);
        if ($project === null) return $this->json(['error' => 'Project not found'], Response::HTTP_NOT_FOUND);

        $link = $this->findByUlid(ExternalStatementLink::class, $lid);
        if (!$link instanceof ExternalStatementLink || $link->getExternalStatement()->getProject()->getId()->toRfc4122() !== $project->getId()->toRfc4122()) {
            return $this->json(['error' => 'External statement link not found in this project'], Response::HTTP_NOT_FOUND);
        }

        $body = $this->jsonBody($request);
        $reviewer = trim((string) ($body['reviewed_by'] ?? ''));
        if ($reviewer === '') return $this->json(['error' => 'reviewed_by is required: link decisions are human decisions.'], Response::HTTP_UNPROCESSABLE_ENTITY);

        $before = $this->serializeLink($link);
        if (array_key_exists('relationship', $body)) $link->setRelationship((string) $body['relationship']);
        if (array_key_exists('comparability_note', $body)) $link->setComparabilityNote($this->nullableString($body['comparability_note']));
        $link->setReviewStatus($decision)->setReviewedBy($reviewer)->setReviewedAt(new \DateTimeImmutable())
            ->setReviewerNote($this->nullableString($body['reviewer_note'] ?? $link->getReviewerNote()));

        $violations = $this->validator->validate($link);
        if (count($violations) > 0) return $this->validationError($violations);
        $this->em->flush();

        $after = $this->serializeLink($link);
        $this->audit->log($project, 'ExternalStatementLink', $link->getId()->toRfc4122(), $decision === ExternalStatementLink::REVIEW_APPROVED ? 'approve' : 'reject',
            $before, $after, isset($body['reason']) ? (string) $body['reason'] : $link->getReviewerNote(), $reviewer);

        return $this->json($after);
    }

    private function resolveTarget(Project $project, ExternalStatementLink $link, string $type, string $ref): ?string
    {
        if ($ref === '') return 'target_id is required.';
        $projectId = $project->getId()->toRfc4122();
        switch ($type) {
            case ExternalStatementLink::TARGET_REQUIREMENT_ASSESSMENT:
                $target = $this->findByUlid(RequirementAssessment::class, $ref);
                if (!$target instanceof RequirementAssessment) {
                    foreach ($this->em->getRepository(RequirementAssessment::class)->findBy(['project' => $project]) as $candidate) {
                        if ($candidate->getRequirement()->getRequirementKey() === $ref) { $target = $candidate; break; }
                    }
                }
                if (!$target instanceof RequirementAssessment || $target->getProject()->getId()->toRfc4122() !== $projectId) return 'Requirement assessment not found in this project.';
                $link->setRequirementAssessment($target);
                return null;
            case ExternalStatementLink::TARGET_CLAIM:
                $target = $this->findByUlid(ClaimNode::class, $ref) ?? $this->em->getRepository(ClaimNode::class)->findOneBy(['project' => $project, 'claimId' => $ref]);
                if (!$target instanceof ClaimNode || $target->getProject()->getId()->toRfc4122() !== $projectId) return 'Claim not found in this project.';
                $link->setClaim($target);
                return null;
            case ExternalStatementLink::TARGET_EVIDENCE_ITEM:
                $target = $this->findByUlid(EvidenceItem::class, $ref) ?? $this->em->getRepository(EvidenceItem::class)->findOneBy(['evidenceId' => $ref]);
                if (!$target instanceof EvidenceItem || $target->getStudy()->getProject()->getId()->toRfc4122() !== $projectId) return 'Evidence item not found in this project.';
                $link->setEvidenceItem($target);
                return null;
            default:
                return 'target_type must be requirement_assessment, claim or evidence_item.';
        }
    }

    /** @return list<ExternalStatementLink> */
    private function linksForProject(Project $project): array
    {
        return $this->em->createQueryBuilder()
            ->select('l')->from(ExternalStatementLink::class, 'l')
            ->join('l.externalStatement', 's')
            ->where('IDENTITY(s.project) = :pid')->setParameter('pid', $project->getId()->toRfc4122())
            ->orderBy('l.createdAt', 'ASC')
            ->getQuery()->getResult();
    }

    /** @return array<string,mixed> */
    private function serializeStatement(ExternalStatement $s): array
    {
        /** @var ExternalStatementLink[] $links */
        $links = $this->em->getRepository(ExternalStatementLink::class)->findBy(['externalStatement' => $s], ['createdAt' => 'ASC']);
        return [
            'id' => $s->getId()->toRfc4122(),
            'project_id' => $s->getProject()->getId()->toRfc4122(),
            'source_system' => $s->getSourceSystem(),
            'statement_uri' => $s->getStatementUri(),
            'record_uri' => $s->getRecordUri(),
            'original_work_doi' => $s->getOriginalWorkDoi(),
            'source_version' => $s->getSourceVersion(),
            'statement_text' => $s->getStatementText(),
            'statement_kind' => $s->getStatementKind(),
            'authors' => $s->getAuthors(),
            'dataset_links' => $s->getDatasetLinks(),
            'code_links' => $s->getCodeLinks(),
            'study_context' => $s->getStudyContext(),
            'uncertainty' => $s->getUncertainty(),
            'licence' => $s->getLicence(),
            'access_scope' => $s->getAccessScope(),
            'payload_sha256' => $s->getPayloadSha256(),
            'captured_at' => $s->getCapturedAt()->format(\DateTimeInterface::ATOM),
            'captured_by' => $s->getCapturedBy(),
            'capture_status' => $s->getCaptureStatus(),
            'verified_by' => $s->getVerifiedBy(),
            'verified_at' => $s->getVerifiedAt()?->format(\DateTimeInterface::ATOM),
            'eligible_for_support' => $s->isEligibleForSupport(),
            'exportable' => $s->isExportable(),
            'raw_payload' => $s->getRawPayload(),
            'links' => array_map($this->serializeLink(...), $links),
        ];
    }

    /** @return array<string,mixed> */
    private function serializeLink(ExternalStatementLink $l): array
    {
        $target = match ($l->getTargetType()) {
            ExternalStatementLink::TARGET_REQUIREMENT_ASSESSMENT => [
                'id' => $l->getRequirementAssessment()?->getId()->toRfc4122(),
                'key' => $l->getRequirementAssessment()?->getRequirement()->getRequirementKey(),
                'label' => $l->getRequirementAssessment()?->getRequirement()->getTitle(),
                'status' => $l->getRequirementAssessment()?->getStatus(),
            ],
            ExternalStatementLink::TARGET_CLAIM => [
                'id' => $l->getClaim()?->getId()->toRfc4122(),
                'key' => $l->getClaim()?->getClaimId(),
                'label' => $l->getClaim()?->getClaimText(),
                'status' => $l->getClaim()?->getReviewStatus(),
            ],
            default => [
                'id' => $l->getEvidenceItem()?->getId()->toRfc4122(),
                'key' => $l->getEvidenceItem()?->getEvidenceId(),
                'label' => $l->getEvidenceItem()?->getQuestion(),
                'status' => $l->getEvidenceItem()?->getStatus(),
            ],
        };
        $s = $l->getExternalStatement();
        return [
            'id' => $l->getId()->toRfc4122(),
            'external_statement_id' => $s->getId()->toRfc4122(),
            'statement_uri' => $s->getStatementUri(),
            'statement_text' => $s->getStatementText(),
            'target_type' => $l->getTargetType(),
            'target' => $target,
            'relationship' => $l->getRelationship(),
            'link_origin' => $l->getLinkOrigin(),
            'review_status' => $l->getReviewStatus(),
            'reviewed_by' => $l->getReviewedBy(),
            'reviewed_at' => $l->getReviewedAt()?->format(\DateTimeInterface::ATOM),
            'reviewer_note' => $l->getReviewerNote(),
            'comparability_note' => $l->getComparabilityNote(),
            'provenance' => $l->getProvenance(),
            'counts_as_evidence' => false,
            'created_at' => $l->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }

    private function findStatement(Project $project, string $sid): ?ExternalStatement
    {
        $statement = $this->findByUlid(ExternalStatement::class, $sid);
        if (!$statement instanceof ExternalStatement) return null;
        return $statement->getProject()->getId()->toRfc4122() === $project->getId()->toRfc4122() ? $statement : null;
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

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) return null;
        $value = trim((string) $value);
        return $value === '' ? null : $value;
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

    private function validationError(ConstraintViolationListInterface $violations): JsonResponse
    {
        $errors = [];
        foreach ($violations as $violation) {
            $errors[] = ['field' => $violation->getPropertyPath(), 'message' => $violation->getMessage()];
        }
        return $this->json(['error' => 'Validation failed', 'violations' => $errors], Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
