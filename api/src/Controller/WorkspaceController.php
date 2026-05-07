<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\ClaimEdge;
use App\Entity\ClaimNode;
use App\Entity\ContextOfUseCard;
use App\Entity\ECTDMapping;
use App\Entity\EvidenceItem;
use App\Entity\NAMStudy;
use App\Entity\Project;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Uid\Ulid;

#[Route('/api/v1', name: 'api_v1_')]
class WorkspaceController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly SerializerInterface $serializer,
    ) {}

    #[Route('/projects', name: 'projects_list', methods: ['GET'])]
    public function listProjects(): JsonResponse
    {
        $projects = $this->em->getRepository(Project::class)->findBy([], ['updatedAt' => 'DESC']);

        return $this->json(array_map($this->normalise(...), $projects));
    }

    #[Route('/projects', name: 'projects_create', methods: ['POST'])]
    public function createProject(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->json(['error' => 'Invalid JSON body'], Response::HTTP_BAD_REQUEST);
        }

        if (!isset($data['name'], $data['drug_name'])) {
            return $this->json(['error' => 'name and drug_name are required'], Response::HTTP_BAD_REQUEST);
        }

        $project = new Project();
        $project->setName((string) $data['name']);
        $project->setDescription(isset($data['description']) ? (string) $data['description'] : null);
        $project->setDrugName((string) $data['drug_name']);
        $project->setSponsor(isset($data['sponsor']) ? (string) $data['sponsor'] : null);

        $this->em->persist($project);
        $this->em->flush();

        return $this->json($this->normalise($project), Response::HTTP_CREATED);
    }

    #[Route('/projects/{id}/workspace', name: 'workspace_get', methods: ['GET'])]
    public function getWorkspace(string $id): JsonResponse
    {
        $project = $this->findProject($id);
        if ($project === null) {
            return $this->json(['error' => 'Project not found'], Response::HTTP_NOT_FOUND);
        }

        $cous = $this->em->getRepository(ContextOfUseCard::class)->findBy(['project' => $project], ['updatedAt' => 'DESC']);
        $studies = $this->em->getRepository(NAMStudy::class)->findBy(['project' => $project], ['createdAt' => 'DESC']);
        $claims = $this->em->getRepository(ClaimNode::class)->findBy(['project' => $project]);

        $claimEdgesById = [];
        foreach ($claims as $claim) {
            foreach ($this->em->getRepository(ClaimEdge::class)->findBy(['fromClaim' => $claim]) as $edge) {
                $claimEdgesById[(string) $edge->getId()] = $edge;
            }
            foreach ($this->em->getRepository(ClaimEdge::class)->findBy(['toClaim' => $claim]) as $edge) {
                $claimEdgesById[(string) $edge->getId()] = $edge;
            }
        }

        $mappingsById = [];
        foreach ($studies as $study) {
            foreach ($this->em->getRepository(ECTDMapping::class)->findBy(['study' => $study]) as $mapping) {
                $mappingsById[(string) $mapping->getId()] = $mapping;
            }
        }
        foreach ($claims as $claim) {
            foreach ($this->em->getRepository(ECTDMapping::class)->findBy(['claim' => $claim]) as $mapping) {
                $mappingsById[(string) $mapping->getId()] = $mapping;
            }
        }

        $evidenceItems = [];
        foreach ($studies as $study) {
            foreach ($study->getEvidenceItems() as $item) {
                $evidenceItems[] = $item;
            }
        }

        return $this->json([
            'project' => $this->normalise($project),
            'context_of_use_cards' => array_map($this->normalise(...), $cous),
            'nam_studies' => array_map($this->normalise(...), $studies),
            'evidence_items' => array_map($this->normalise(...), $evidenceItems),
            'claim_nodes' => array_map($this->normalise(...), $claims),
            'claim_edges' => array_map($this->normalise(...), array_values($claimEdgesById)),
            'ectd_mappings' => array_map($this->normalise(...), array_values($mappingsById)),
        ]);
    }

    #[Route('/projects/{id}/cou/{couId}', name: 'cou_update', methods: ['PUT'])]
    public function updateCou(string $id, string $couId, Request $request): JsonResponse
    {
        $project = $this->findProject($id);
        if ($project === null) {
            return $this->json(['error' => 'Project not found'], Response::HTTP_NOT_FOUND);
        }

        $cou = $this->em->getRepository(ContextOfUseCard::class)->findOneBy(['couId' => $couId]);
        if ($cou === null || (string) $cou->getProject()->getId() !== (string) $project->getId()) {
            return $this->json(['error' => 'COU not found for this project'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->json(['error' => 'Invalid JSON body'], Response::HTTP_BAD_REQUEST);
        }

        $map = [
            'regulatory_question' => fn(string $v) => $cou->setRegulatoryQuestion($v),
            'intended_use' => fn(string $v) => $cou->setIntendedUse($v),
            'decision_supported' => fn(string $v) => $cou->setDecisionSupported($v),
            'drug_development_stage' => fn(string $v) => $cou->setDrugDevelopmentStage($v),
            'biological_domain' => fn(string $v) => $cou->setBiologicalDomain($v),
            'endpoint_class' => fn(string $v) => $cou->setEndpointClass($v),
            'population_relevance' => fn(?string $v) => $cou->setPopulationRelevance($v),
            'regulatory_confidence_level' => fn(string $v) => $cou->setRegulatoryConfidenceLevel($v),
        ];

        foreach ($map as $field => $setter) {
            if (array_key_exists($field, $data)) {
                $setter($data[$field]);
            }
        }

        if (array_key_exists('limitations', $data) && is_array($data['limitations'])) {
            $cou->setLimitations($data['limitations']);
        }

        if (array_key_exists('acceptance_criteria', $data) && is_array($data['acceptance_criteria'])) {
            $cou->setAcceptanceCriteria($data['acceptance_criteria']);
        }

        $this->em->flush();

        return $this->json($this->normalise($cou));
    }

    #[Route('/projects/{id}/evidence/{evidenceId}', name: 'evidence_update', methods: ['PUT'])]
    public function updateEvidence(string $id, string $evidenceId, Request $request): JsonResponse
    {
        $project = $this->findProject($id);
        if ($project === null) {
            return $this->json(['error' => 'Project not found'], Response::HTTP_NOT_FOUND);
        }

        $item = $this->em->getRepository(EvidenceItem::class)->findOneBy(['evidenceId' => $evidenceId]);
        if ($item === null || (string) $item->getStudy()->getProject()->getId() !== (string) $project->getId()) {
            return $this->json(['error' => 'Evidence item not found for this project'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->json(['error' => 'Invalid JSON body'], Response::HTTP_BAD_REQUEST);
        }

        if (isset($data['status'])) {
            $item->setStatus((string) $data['status']);
        }

        if (array_key_exists('notes', $data)) {
            $item->setNotes($data['notes'] !== null ? (string) $data['notes'] : null);
        }

        $this->em->flush();

        return $this->json($this->normalise($item));
    }

    #[Route('/projects/{id}/claims/{claimId}/status', name: 'claim_status_update', methods: ['PUT'])]
    public function updateClaimStatus(string $id, string $claimId, Request $request): JsonResponse
    {
        $project = $this->findProject($id);
        if ($project === null) {
            return $this->json(['error' => 'Project not found'], Response::HTTP_NOT_FOUND);
        }

        $claim = $this->em->getRepository(ClaimNode::class)->findOneBy(['claimId' => $claimId]);
        if ($claim === null || (string) $claim->getProject()->getId() !== (string) $project->getId()) {
            return $this->json(['error' => 'Claim not found for this project'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);
        if (!is_array($data) || !isset($data['status'])) {
            return $this->json(['error' => 'status is required'], Response::HTTP_BAD_REQUEST);
        }

        $claim->setReviewStatus((string) $data['status']);
        $this->em->flush();

        return $this->json($this->normalise($claim));
    }

    private function findProject(string $id): ?Project
    {
        try {
            return $this->em->find(Project::class, Ulid::fromString($id)->toRfc4122());
        } catch (\Throwable) {
            return null;
        }
    }

    private function normalise(object $entity): array
    {
        $json = $this->serializer->serialize($entity, 'json', ['groups' => ['read']]);

        return json_decode($json, true) ?? [];
    }
}
