<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\ClaimNode;
use App\Entity\ECTDMapping;
use App\Entity\EvidenceItem;
use App\Entity\ExportPackage;
use App\Entity\NAMStudy;
use App\Entity\Project;
use App\Service\Export\ExportGate;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Uid\Ulid;

#[Route('/api/projects/{id}/export', name: 'api_project_export_')]
class ExportController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly SerializerInterface $serializer,
    ) {}

    /**
     * Generate a complete evidence package snapshot for a project and return
     * it as a JSON response. Also persists the snapshot as an ExportPackage
     * for audit / archival purposes.
     *
     * Pre-condition: all ClaimNodes in the project must have review_status = 'approved'.
     * If any are still in 'human_review_required', the endpoint returns 422.
     */
    #[Route('', name: 'generate', methods: ['POST'])]
    public function generate(string $id): JsonResponse
    {
        $project = $this->em->find(Project::class, Ulid::fromString($id));
        if ($project === null) {
            return $this->json(['error' => 'Project not found'], Response::HTTP_NOT_FOUND);
        }

        // Human-review gate (delegated to App\Service\Export\ExportGate so the predicate is unit-testable).
        $allClaims = $this->em->getRepository(ClaimNode::class)->findBy(['project' => $project]);
        if (ExportGate::isBlocked($allClaims)) {
            return $this->json([
                'error'       => 'Export blocked: human review required',
                'pending_ids' => ExportGate::blockingClaimIds($allClaims),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Assemble payload
        $studies      = $this->em->getRepository(NAMStudy::class)->findBy(['project' => $project]);
        $claimNodes   = $allClaims;
        $ectdMappings = $this->em->getRepository(ECTDMapping::class)->findAll(); // filter by project in production

        $evidenceItems = [];
        foreach ($studies as $study) {
            foreach ($study->getEvidenceItems() as $item) {
                $evidenceItems[] = $item;
            }
        }

        $payload = [
            'package_id'     => 'PKG-' . (string) new Ulid(),
            'schema_version' => '1.0.0',
            'tool'           => 'NAMO-to-IND Mapper API',
            'exported_at'    => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            'project'        => $this->normalise($project),
            'nam_studies'    => array_map($this->normalise(...), $studies),
            'evidence_items' => array_map($this->normalise(...), $evidenceItems),
            'claim_nodes'    => array_map($this->normalise(...), $claimNodes),
            'ectd_mappings'  => array_map($this->normalise(...), $ectdMappings),
        ];

        // Persist snapshot
        $pkg = new ExportPackage();
        $pkg->setPackageId($payload['package_id']);
        $pkg->setProject($project);
        $pkg->setPayload($payload);
        $this->em->persist($pkg);
        $this->em->flush();

        return $this->json($payload, Response::HTTP_CREATED);
    }

    /**
     * List previously generated export snapshots for a project.
     */
    #[Route('/history', name: 'history', methods: ['GET'])]
    public function history(string $id): JsonResponse
    {
        $project = $this->em->find(Project::class, Ulid::fromString($id));
        if ($project === null) {
            return $this->json(['error' => 'Project not found'], Response::HTTP_NOT_FOUND);
        }

        $packages = $this->em->getRepository(ExportPackage::class)->findBy(
            ['project' => $project],
            ['exportedAt' => 'DESC']
        );

        return $this->json(array_map(function (ExportPackage $pkg) {
            return [
                'package_id'  => $pkg->getPackageId(),
                'exported_at' => $pkg->getExportedAt()->format(\DateTimeInterface::ATOM),
                'version'     => $pkg->getVersion(),
            ];
        }, $packages));
    }

    /** Normalise an entity to a plain array using the Symfony Serializer. */
    private function normalise(object $entity): array
    {
        $json = $this->serializer->serialize($entity, 'json', ['groups' => ['read']]);
        return json_decode($json, true) ?? [];
    }
}
