<?php

declare(strict_types=1);

namespace App\Controller\V1;

use App\Entity\NamCore\EndpointMeasurement;
use App\Entity\Project;
use App\Service\NamCore\AuditLogger;
use App\Service\NamCore\EndpointMeasurementImporter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Ulid;

/**
 * NAM-CORE endpoint-measurement standardization API (v1).
 *
 *   POST /api/v1/projects/{id}/endpoint-measurements/import
 *        body: { csv: string, mapping?: {header:field}, dry_run?: bool }
 *        - no mapping  → returns a column preview + auto-suggested mapping
 *        - with mapping → validates, normalizes units, stores, returns a summary
 *   GET  /api/v1/projects/{id}/endpoint-measurements
 */
#[Route('/api/v1/projects/{id}/endpoint-measurements')]
class EndpointMeasurementController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly EndpointMeasurementImporter $importer,
        private readonly AuditLogger $audit,
    ) {}

    #[Route('/import', name: 'v1_epm_import', methods: ['POST'])]
    public function import(string $id, Request $request): JsonResponse
    {
        $project = $this->findProject($id);
        if ($project === null) {
            return $this->json(['error' => 'Project not found'], Response::HTTP_NOT_FOUND);
        }

        $csv = $this->extractCsv($request);
        if ($csv === null || trim($csv) === '') {
            return $this->json(['error' => 'No CSV content provided (send { "csv": "..." } or multipart "file").'], Response::HTTP_BAD_REQUEST);
        }

        $payload = $this->jsonBody($request);
        $mapping = is_array($payload['mapping'] ?? null) ? $payload['mapping'] : null;
        $dryRun = (bool) ($payload['dry_run'] ?? false);

        if ($mapping === null || count($mapping) === 0) {
            return $this->json([
                'mode'    => 'preview',
                'preview' => $this->importer->preview($csv),
                'target_fields' => EndpointMeasurementImporter::TARGET_FIELDS,
            ]);
        }

        /** @var array<string,string> $mapping */
        $summary = $this->importer->import($project, $csv, $mapping, $dryRun);
        if (!$dryRun) {
            $this->audit->log($project, 'EndpointMeasurement', null, 'import', null, [
                'imported' => $summary['imported'],
                'errors'   => $summary['error_count'],
                'warnings' => $summary['warning_count'],
            ], 'CSV endpoint-measurement import');
        }

        return $this->json(['mode' => $dryRun ? 'dry_run' : 'import', 'summary' => $summary], Response::HTTP_CREATED);
    }

    #[Route('', name: 'v1_epm_list', methods: ['GET'])]
    public function list(string $id): JsonResponse
    {
        $project = $this->findProject($id);
        if ($project === null) {
            return $this->json(['error' => 'Project not found'], Response::HTTP_NOT_FOUND);
        }

        $rows = $this->em->getRepository(EndpointMeasurement::class)->findBy(['project' => $project], ['endpointId' => 'ASC']);

        return $this->json([
            'count'        => count($rows),
            'measurements' => array_map($this->normalize(...), $rows),
        ]);
    }

    private function normalize(EndpointMeasurement $m): array
    {
        return [
            'id'                => $m->getId()->toRfc4122(),
            'endpoint_id'       => $m->getEndpointId(),
            'endpoint_label'    => $m->getEndpointLabel(),
            'endpoint_iri'      => $m->getEndpointOntologyIri(),
            'value'             => $m->getValue(),
            'value_raw'         => $m->getValueRaw(),
            'unit'              => $m->getUnit(),
            'unit_iri'          => $m->getUnitOntologyIri(),
            'timepoint_value'   => $m->getTimepointValue(),
            'timepoint_unit'    => $m->getTimepointUnit(),
            'replicate_id'      => $m->getReplicateId(),
            'batch_id'          => $m->getBatchId(),
            'qc_status'         => $m->getQcStatus(),
            'exclusion_status'  => $m->getExclusionStatus(),
            'exclusion_reason'  => $m->getExclusionReason(),
            'validation_status' => $m->getValidationStatus(),
            'study'             => $m->getStudy()?->getStudyId(),
            'sample'            => $m->getSample()?->getSampleCode(),
            'assay'             => $m->getAssay()?->getLabel(),
            'exposure'          => $m->getExposure()?->getTestArticle(),
            'raw_file'          => $m->getRawDataFile()?->getFileName(),
            'unresolved'        => array_filter($m->getExtensions(), static fn($k) => str_starts_with((string) $k, 'unresolved_'), ARRAY_FILTER_USE_KEY),
        ];
    }

    private function extractCsv(Request $request): ?string
    {
        $file = $request->files->get('file');
        if ($file !== null) {
            return (string) file_get_contents($file->getPathname());
        }
        $body = $this->jsonBody($request);
        if (isset($body['csv']) && is_string($body['csv'])) {
            return $body['csv'];
        }
        // raw text/csv body
        $content = (string) $request->getContent();
        if (str_contains((string) $request->headers->get('Content-Type'), 'csv')) {
            return $content;
        }
        return null;
    }

    /** @return array<string,mixed> */
    private function jsonBody(Request $request): array
    {
        $content = (string) $request->getContent();
        if ($content === '') {
            return [];
        }
        try {
            $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
            return is_array($decoded) ? $decoded : [];
        } catch (\JsonException) {
            return [];
        }
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
