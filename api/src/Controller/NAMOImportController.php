<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\ContextOfUseCard;
use App\Entity\NAMStudy;
use App\Entity\Project;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Imports a NAMO-aligned NAM study payload (JSON or YAML) into a project.
 *
 * The body is mapped onto a NAMStudy record bound to the project's first
 * Context-of-Use card; subsequent COU selection is the frontend's job.
 */
#[Route('/api/projects/{id}/namo-import', name: 'api_project_namo_import')]
class NAMOImportController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {}

    #[Route('', methods: ['POST'])]
    public function import(string $id, Request $request): JsonResponse
    {
        $project = $this->em->find(Project::class, Ulid::fromString($id));
        if ($project === null) {
            return $this->json(['error' => 'Project not found'], Response::HTTP_NOT_FOUND);
        }

        $body = $request->getContent();
        if ($body === '') {
            return $this->json(['error' => 'Empty request body'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $payload = $this->parseBody($body, (string) $request->headers->get('Content-Type', ''));
        } catch (\JsonException | ParseException $e) {
            return $this->json(
                ['error' => 'Could not parse payload', 'detail' => $e->getMessage()],
                Response::HTTP_BAD_REQUEST,
            );
        }

        if (!is_array($payload)) {
            return $this->json(
                ['error' => 'Payload must be an object/mapping at the root'],
                Response::HTTP_BAD_REQUEST,
            );
        }

        // Resolve the COU. Caller may supply context_of_use_id explicitly; otherwise we pick
        // the first COU on the project. Reject if neither resolves.
        $couId = $payload['context_of_use_id'] ?? null;
        $cou = null;
        if (is_string($couId) && $couId !== '') {
            try {
                $cou = $this->em->find(ContextOfUseCard::class, Ulid::fromString($couId));
            } catch (\InvalidArgumentException) {
                $cou = null;
            }
        }
        if ($cou === null) {
            $existing = $this->em->getRepository(ContextOfUseCard::class)
                ->findBy(['project' => $project], ['createdAt' => 'ASC'], 1);
            $cou = $existing[0] ?? null;
        }
        if ($cou === null) {
            return $this->json(
                ['error' => 'Project has no Context-of-Use card; create one before importing studies.'],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $study = new NAMStudy();
        $study->setProject($project);
        $study->setContextOfUse($cou);

        $studyId = (string) ($payload['id'] ?? $payload['study_id'] ?? ('STU-' . (string) new Ulid()));
        $study->setStudyId($studyId);
        $study->setTitle((string) ($payload['name'] ?? $payload['title'] ?? ''));

        $study->setModelSystem($this->asArray($payload['model_system'] ?? []));
        $study->setExperimentalDesign($this->asArray($payload['experimental_design'] ?? []));
        $study->setAssayMetadata($this->asArray($payload['assay_metadata'] ?? []));
        $study->setDataOutputs($this->asArray($payload['data_outputs'] ?? []));
        $study->setProvenance($this->asArray($payload['provenance'] ?? []));

        $this->em->persist($study);

        try {
            $this->em->flush();
        } catch (\Throwable $e) {
            return $this->json(
                ['error' => 'Could not persist study', 'detail' => $e->getMessage()],
                Response::HTTP_BAD_REQUEST,
            );
        }

        // API Platform exposes the canonical IRI under /api/nam_studies/{ulid}
        $location = sprintf('/api/nam_studies/%s', (string) $study->getId());

        return $this->json(
            [
                'study_id' => $study->getStudyId(),
                'id'       => (string) $study->getId(),
                'url'      => $location,
            ],
            Response::HTTP_CREATED,
            ['Location' => $location],
        );
    }

    private function parseBody(string $body, string $contentType): mixed
    {
        $isYaml = str_contains($contentType, 'yaml')
            || str_contains($contentType, 'yml');

        if ($isYaml) {
            return Yaml::parse($body);
        }

        // Default: JSON. If parse fails AND no content-type was set, fall back to YAML.
        try {
            return json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            if ($contentType === '' || str_contains($contentType, 'text/plain')) {
                return Yaml::parse($body);
            }
            throw $e;
        }
    }

    private function asArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if ($value === null || $value === '') {
            return [];
        }
        return ['value' => $value];
    }
}
