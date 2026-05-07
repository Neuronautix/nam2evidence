<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\ClaimNode;
use App\Entity\ECTDMapping;
use App\Entity\EvidenceItem;
use App\Entity\ExportPackage;
use App\Entity\NAMStudy;
use App\Entity\Project;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr\Join;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
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
        $project = $this->findProject($id);
        if ($project === null) {
            return $this->json(['error' => 'Project not found'], Response::HTTP_NOT_FOUND);
        }

        $pendingIds = $this->getPendingClaimIds($project);
        if (count($pendingIds) > 0) {
            return $this->json([
                'error'       => 'Export blocked: human review required',
                'pending_ids' => $pendingIds,
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $payload = $this->buildPayload($project);
        $this->persistPackage($project, $payload);

        return $this->json($payload, Response::HTTP_CREATED);
    }

    /**
     * Generate a snapshot and stream it as a file artifact.
     */
    #[Route('/download', name: 'download', methods: ['POST'])]
    public function generateAndDownload(string $id, Request $request): Response
    {
        $project = $this->findProject($id);
        if ($project === null) {
            return $this->json(['error' => 'Project not found'], Response::HTTP_NOT_FOUND);
        }

        $pendingIds = $this->getPendingClaimIds($project);
        if (count($pendingIds) > 0) {
            return $this->json([
                'error' => 'Export blocked: human review required',
                'pending_ids' => $pendingIds,
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $format = $this->parseFormat($request);
        if ($format === null) {
            return $this->json(['error' => 'Invalid export format'], Response::HTTP_BAD_REQUEST);
        }

        $payload = $this->buildPayload($project);
        $this->persistPackage($project, $payload);

        $slug = $this->slugify($project->getDrugName());
        [$content, $mimeType, $extension] = $this->renderArtifact($payload, $format);
        $filename = sprintf('%s_namo-evidence-package.%s', $slug, $extension);

        return new Response($content, Response::HTTP_OK, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => sprintf('attachment; filename="%s"', $filename),
        ]);
    }

    /**
     * List previously generated export snapshots for a project.
     */
    #[Route('/history', name: 'history', methods: ['GET'])]
    public function history(string $id): JsonResponse
    {
        $project = $this->findProject($id);
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

    private function buildPayload(Project $project): array
    {
        $studies = $this->em->getRepository(NAMStudy::class)->findBy(['project' => $project]);
        $claimNodes = $this->em->getRepository(ClaimNode::class)->findBy(['project' => $project]);
        $evidenceItems = count($studies) > 0
            ? $this->em->getRepository(EvidenceItem::class)->findBy(['study' => $studies])
            : [];
        $ectdMappings = $this->em->createQueryBuilder()
            ->select('m')
            ->from(ECTDMapping::class, 'm')
            ->leftJoin('m.study', 's', Join::WITH)
            ->leftJoin('m.claim', 'c', Join::WITH)
            ->where('s.project = :project OR c.project = :project')
            ->setParameter('project', $project)
            ->getQuery()
            ->getResult();

        return [
            'package_id' => 'PKG-' . (string) new Ulid(),
            'schema_version' => '1.0.0',
            'tool' => 'NAMO-to-IND Mapper API',
            'exported_at' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            'project' => $this->normalise($project),
            'nam_studies' => array_map($this->normalise(...), $studies),
            'evidence_items' => array_map($this->normalise(...), $evidenceItems),
            'claim_nodes' => array_map($this->normalise(...), $claimNodes),
            'ectd_mappings' => array_map($this->normalise(...), $ectdMappings),
        ];
    }

    private function persistPackage(Project $project, array $payload): void
    {
        $pkg = new ExportPackage();
        $pkg->setPackageId((string) $payload['package_id']);
        $pkg->setProject($project);
        $pkg->setPayload($payload);
        $this->em->persist($pkg);
        $this->em->flush();
    }

    private function findProject(string $id): ?Project
    {
        try {
            return $this->em->find(Project::class, Ulid::fromString($id));
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return string[] */
    private function getPendingClaimIds(Project $project): array
    {
        $pendingClaims = $this->em->getRepository(ClaimNode::class)
            ->findBy(['project' => $project, 'reviewStatus' => 'human_review_required']);

        return array_values(array_map(fn(ClaimNode $claim) => $claim->getClaimId(), $pendingClaims));
    }

    private function parseFormat(Request $request): ?string
    {
        $format = strtolower((string) $request->query->get('format', 'json'));
        $allowed = ['json', 'csv', 'md', 'txt'];

        return in_array($format, $allowed, true) ? $format : null;
    }

    /** @return array{0:string,1:string,2:string} */
    private function renderArtifact(array $payload, string $format): array
    {
        if ($format === 'json') {
            return [json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}', 'application/json', 'json'];
        }

        if ($format === 'csv') {
            $rows = [];
            $rows[] = ['Evidence ID', 'Domain', 'Question', 'Evidence Type', 'Status', 'Notes', 'Supporting Data'];
            foreach ($payload['evidence_items'] ?? [] as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $rows[] = [
                    (string) ($item['evidenceId'] ?? ''),
                    (string) ($item['domain'] ?? ''),
                    (string) ($item['question'] ?? ''),
                    (string) ($item['evidenceType'] ?? ''),
                    (string) ($item['status'] ?? ''),
                    (string) ($item['notes'] ?? ''),
                    (string) ($item['supportingData'] ?? ''),
                ];
            }

            $lines = array_map(static function (array $row): string {
                $escaped = array_map(static function (string $value): string {
                    $clean = str_replace('"', '""', $value);
                    return '"' . $clean . '"';
                }, $row);

                return implode(',', $escaped);
            }, $rows);

            return [implode("\n", $lines), 'text/csv; charset=utf-8', 'csv'];
        }

        if ($format === 'md') {
            $project = is_array($payload['project'] ?? null) ? $payload['project'] : [];
            $claims = is_array($payload['claim_nodes'] ?? null) ? $payload['claim_nodes'] : [];
            $mappings = is_array($payload['ectd_mappings'] ?? null) ? $payload['ectd_mappings'] : [];

            $lines = [
                '# NAM Evidence Dossier',
                '',
                'Project: ' . (string) ($project['name'] ?? ''),
                'Drug: ' . (string) ($project['drugName'] ?? ''),
                'Exported At: ' . (string) ($payload['exported_at'] ?? ''),
                '',
                '## Claims',
            ];

            foreach ($claims as $claim) {
                if (!is_array($claim)) {
                    continue;
                }

                $lines[] = '- ' . (string) ($claim['claimId'] ?? 'UNKNOWN') . ': ' . (string) ($claim['claimText'] ?? '');
            }

            $lines[] = '';
            $lines[] = '## eCTD Mappings';
            foreach ($mappings as $mapping) {
                if (!is_array($mapping)) {
                    continue;
                }

                $lines[] = '- ' . (string) ($mapping['ectdSection'] ?? '') . ' ' . (string) ($mapping['ectdTitle'] ?? '');
            }

            return [implode("\n", $lines), 'text/markdown; charset=utf-8', 'md'];
        }

        $project = is_array($payload['project'] ?? null) ? $payload['project'] : [];
        $study = null;
        if (!empty($payload['nam_studies']) && is_array($payload['nam_studies'][0] ?? null)) {
            $study = $payload['nam_studies'][0];
        }

        $lines = [
            'eCTD Module 4 Folder Map',
            'Drug: ' . (string) ($project['drugName'] ?? ''),
            'Generated: ' . (string) ($payload['exported_at'] ?? ''),
            '',
            'm4/',
            '|- 4.2-study-reports/',
            '|  |- 4.2.3-toxicology/',
            '|  |  |- 4.2.3.7-other-toxicology/',
            '|  |  |  |- 4.2.3.7.3-other-in-vitro/',
            '|  |  |  |  |- ' . (string) ($study['studyId'] ?? 'study') . '_study-report.pdf',
            '',
            'm2/',
            '|- 2.6.2-pharmacology-written-summary/',
            '|- 2.6.6-toxicology-written-summary/',
        ];

        return [implode("\n", $lines), 'text/plain; charset=utf-8', 'txt'];
    }

    private function slugify(string $value): string
    {
        $slug = preg_replace('/[^a-zA-Z0-9]+/', '-', strtolower($value)) ?? 'project';
        return trim($slug, '-') !== '' ? trim($slug, '-') : 'project';
    }
}
