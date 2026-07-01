<?php

declare(strict_types=1);

namespace App\Controller\V1;

use App\Entity\NamCore\AuditLog;
use App\Entity\Project;
use App\Service\Export\ExportReadinessGate;
use App\Service\NamCore\ProjectGraphBuilder;
use App\Service\NamCore\ReadinessScorer;
use App\Service\NamCore\SemanticValidator;
use App\Service\NamCore\ValidatorSidecarClient;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Ulid;

/**
 * Read-side NAM-CORE reports: semantic validation, readiness, export-gate status,
 * and the project audit log.
 */
#[Route('/api/v1/projects/{id}')]
class NamCoreReportController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly SemanticValidator $validator,
        private readonly ReadinessScorer $scorer,
        private readonly ExportReadinessGate $gate,
        private readonly ProjectGraphBuilder $graph,
        private readonly ValidatorSidecarClient $sidecar,
    ) {}

    #[Route('/semantic-validation', name: 'v1_semantic_validation', methods: ['GET', 'POST'])]
    public function semanticValidation(string $id): JsonResponse
    {
        $project = $this->findProject($id);
        if ($project === null) {
            return $this->json(['error' => 'Project not found'], Response::HTTP_NOT_FOUND);
        }

        $report = $this->validator->validate($project);

        // Optional second opinion from the pyshacl sidecar (graceful if absent).
        $sidecarReport = $this->sidecar->validate($this->graph->toJsonLd($project));
        $report['shacl_sidecar'] = $sidecarReport !== null
            ? ['available' => true, 'conforms' => $sidecarReport['conforms'] ?? null, 'violation_count' => $sidecarReport['violation_count'] ?? null]
            : ['available' => false, 'note' => 'pyshacl sidecar not configured; PHP-native validation used.'];

        return $this->json($report);
    }

    #[Route('/readiness-report', name: 'v1_readiness_report', methods: ['GET'])]
    public function readiness(string $id): JsonResponse
    {
        $project = $this->findProject($id);
        if ($project === null) {
            return $this->json(['error' => 'Project not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->scorer->score($project));
    }

    #[Route('/export-gate', name: 'v1_export_gate', methods: ['GET'])]
    public function exportGate(string $id): JsonResponse
    {
        $project = $this->findProject($id);
        if ($project === null) {
            return $this->json(['error' => 'Project not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->gate->evaluate($project));
    }

    #[Route('/audit-log', name: 'v1_audit_log', methods: ['GET'])]
    public function auditLog(string $id): JsonResponse
    {
        $project = $this->findProject($id);
        if ($project === null) {
            return $this->json(['error' => 'Project not found'], Response::HTTP_NOT_FOUND);
        }

        $entries = $this->em->getRepository(AuditLog::class)->findBy(['project' => $project], ['createdAt' => 'DESC'], 500);

        return $this->json([
            'count'   => count($entries),
            'entries' => array_map(static fn(AuditLog $e) => [
                'id'          => $e->getId()->toRfc4122(),
                'entity_type' => $e->getEntityType(),
                'entity_id'   => $e->getEntityId(),
                'action'      => $e->getAction(),
                'old_value'   => $e->getOldValue(),
                'new_value'   => $e->getNewValue(),
                'user_or_role'=> $e->getUserOrRole(),
                'reason'      => $e->getReason(),
                'timestamp'   => $e->getCreatedAt()->format(\DateTimeInterface::ATOM),
            ], $entries),
        ]);
    }

    private function findProject(string $id): ?Project
    {
        try {
            return $this->em->find(Project::class, Ulid::fromString($id)->toRfc4122());
        } catch (\Throwable) {
            return null;
        }
    }
}
