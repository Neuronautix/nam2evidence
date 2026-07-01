<?php

declare(strict_types=1);

namespace App\Controller\V1;

use App\Entity\NamCore\OntologyMapping;
use App\Entity\NamCore\OntologyTerm;
use App\Entity\Project;
use App\Service\NamCore\AuditLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Ulid;

/**
 * NAM-CORE ontology-mapping API (v1).
 *
 *   GET   /api/v1/ontology/terms                          list / search terms
 *   POST  /api/v1/ontology/terms                          create (idempotent by curie)
 *   POST  /api/v1/ontology/map                            create a mapping (+ auto-suggest)
 *   PATCH /api/v1/ontology/mappings/{id}/approve          human approval
 *   PATCH /api/v1/ontology/mappings/{id}/reject           human rejection
 *   GET   /api/v1/projects/{id}/ontology-mappings         mappings + review summary
 */
class OntologyController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AuditLogger $audit,
    ) {}

    #[Route('/api/v1/ontology/terms', name: 'v1_ontology_terms_list', methods: ['GET'])]
    public function listTerms(Request $request): JsonResponse
    {
        $prefix = $request->query->get('prefix');
        $q = $request->query->get('q');

        /** @var OntologyTerm[] $terms */
        $terms = $this->em->getRepository(OntologyTerm::class)->findAll();

        if (is_string($prefix) && $prefix !== '') {
            $needle = strtolower($prefix);
            $terms = array_filter($terms, static fn(OntologyTerm $t) => strtolower($t->getOntologyPrefix()) === $needle);
        }

        if (is_string($q) && trim($q) !== '') {
            $needle = strtolower(trim($q));
            $terms = array_filter($terms, static function (OntologyTerm $t) use ($needle): bool {
                if (str_contains(strtolower($t->getLabel()), $needle)) {
                    return true;
                }
                if (str_contains(strtolower($t->getCurie()), $needle)) {
                    return true;
                }
                foreach ($t->getSynonyms() as $syn) {
                    if (str_contains(strtolower((string) $syn), $needle)) {
                        return true;
                    }
                }
                return false;
            });
        }

        return $this->json(array_map($this->serializeTerm(...), array_values($terms)));
    }

    #[Route('/api/v1/ontology/terms', name: 'v1_ontology_terms_create', methods: ['POST'])]
    public function createTerm(Request $request): JsonResponse
    {
        $body = $this->jsonBody($request);

        $label = trim((string) ($body['label'] ?? ''));
        $prefix = trim((string) ($body['ontology_prefix'] ?? ''));
        $curie = trim((string) ($body['curie'] ?? ''));

        if ($label === '' || $prefix === '' || $curie === '') {
            return $this->json(
                ['error' => 'label, ontology_prefix and curie are required.'],
                Response::HTTP_BAD_REQUEST,
            );
        }

        $existing = $this->em->getRepository(OntologyTerm::class)->findOneBy(['curie' => $curie]);
        if ($existing !== null) {
            return $this->json($this->serializeTerm($existing), Response::HTTP_OK);
        }

        $synonyms = [];
        if (is_array($body['synonyms'] ?? null)) {
            $synonyms = array_values(array_map(static fn($s) => (string) $s, $body['synonyms']));
        }

        $term = (new OntologyTerm())
            ->setLabel($label)
            ->setOntologyPrefix($prefix)
            ->setCurie($curie)
            ->setIri(isset($body['iri']) ? (string) $body['iri'] : null)
            ->setDefinition(isset($body['definition']) ? (string) $body['definition'] : null)
            ->setSynonyms($synonyms)
            ->setSource(isset($body['source']) ? (string) $body['source'] : null)
            ->setTermVersion(isset($body['term_version']) ? (string) $body['term_version'] : null);

        $this->em->persist($term);
        $this->em->flush();

        $this->audit->log(null, 'OntologyTerm', $term->getId()->toRfc4122(), 'create', null, $this->serializeTerm($term));

        return $this->json($this->serializeTerm($term), Response::HTTP_CREATED);
    }

    #[Route('/api/v1/ontology/map', name: 'v1_ontology_map', methods: ['POST'])]
    public function map(Request $request): JsonResponse
    {
        $body = $this->jsonBody($request);

        $project = $this->findProject((string) ($body['project_id'] ?? ''));
        if ($project === null) {
            return $this->json(['error' => 'Project not found'], Response::HTTP_NOT_FOUND);
        }

        $sourceValue = trim((string) ($body['source_value'] ?? ''));

        $mapping = (new OntologyMapping())
            ->setProject($project)
            ->setSourceEntityType((string) ($body['source_entity_type'] ?? ''))
            ->setSourceValue($sourceValue)
            ->setSourceField(isset($body['source_field']) ? (string) $body['source_field'] : null)
            ->setSourceEntityId(isset($body['source_entity_id']) ? (string) $body['source_entity_id'] : null)
            ->setMandatory((bool) ($body['mandatory'] ?? false));

        $suggested = $this->suggestTerm($sourceValue);
        if ($suggested !== null) {
            $mapping->setOntologyTerm($suggested)
                ->setMappingConfidence(0.6)
                ->setMappingStatus(OntologyMapping::STATUS_SUGGESTED);
        } else {
            $mapping->setMappingConfidence(0.0)
                ->setMappingStatus(OntologyMapping::STATUS_UNMAPPED);
        }

        $this->em->persist($mapping);
        $this->em->flush();

        $this->audit->log($project, 'OntologyMapping', $mapping->getId()->toRfc4122(), 'create', null, $this->serializeMapping($mapping));

        return $this->json($this->serializeMapping($mapping), Response::HTTP_CREATED);
    }

    #[Route('/api/v1/ontology/mappings/{id}/approve', name: 'v1_ontology_approve', methods: ['PATCH'])]
    public function approve(string $id, Request $request): JsonResponse
    {
        $mapping = $this->findMapping($id);
        if ($mapping === null) {
            return $this->json(['error' => 'Mapping not found'], Response::HTTP_NOT_FOUND);
        }

        $body = $this->jsonBody($request);
        $oldStatus = $mapping->getMappingStatus();

        $curie = isset($body['term_curie']) ? trim((string) $body['term_curie']) : '';
        if ($curie !== '') {
            $term = $this->em->getRepository(OntologyTerm::class)->findOneBy(['curie' => $curie]);
            if ($term === null) {
                return $this->json(['error' => sprintf('Ontology term with curie "%s" not found.', $curie)], Response::HTTP_BAD_REQUEST);
            }
            $mapping->setOntologyTerm($term);
        }

        if ($mapping->getOntologyTerm() === null) {
            return $this->json(
                ['error' => 'Cannot approve a mapping with no ontology term'],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $mapping->setMappingStatus(OntologyMapping::STATUS_APPROVED)
            ->setMappingConfidence(1.0);
        if (isset($body['reviewer_note'])) {
            $mapping->setReviewerNote((string) $body['reviewer_note']);
        }
        if (isset($body['reviewed_by'])) {
            $mapping->setReviewedBy((string) $body['reviewed_by']);
        }

        $this->em->flush();

        $this->audit->log(
            $mapping->getProject(),
            'OntologyMapping',
            $mapping->getId()->toRfc4122(),
            'approve',
            ['mapping_status' => $oldStatus],
            ['mapping_status' => $mapping->getMappingStatus()],
        );

        return $this->json($this->serializeMapping($mapping), Response::HTTP_OK);
    }

    #[Route('/api/v1/ontology/mappings/{id}/reject', name: 'v1_ontology_reject', methods: ['PATCH'])]
    public function reject(string $id, Request $request): JsonResponse
    {
        $mapping = $this->findMapping($id);
        if ($mapping === null) {
            return $this->json(['error' => 'Mapping not found'], Response::HTTP_NOT_FOUND);
        }

        $body = $this->jsonBody($request);
        $oldStatus = $mapping->getMappingStatus();

        $mapping->setMappingStatus(OntologyMapping::STATUS_REJECTED);
        if (isset($body['reviewer_note'])) {
            $mapping->setReviewerNote((string) $body['reviewer_note']);
        }
        if (isset($body['reviewed_by'])) {
            $mapping->setReviewedBy((string) $body['reviewed_by']);
        }

        $this->em->flush();

        $this->audit->log(
            $mapping->getProject(),
            'OntologyMapping',
            $mapping->getId()->toRfc4122(),
            'reject',
            ['mapping_status' => $oldStatus],
            ['mapping_status' => $mapping->getMappingStatus()],
        );

        return $this->json($this->serializeMapping($mapping), Response::HTTP_OK);
    }

    #[Route('/api/v1/projects/{id}/ontology-mappings', name: 'v1_ontology_project_mappings', methods: ['GET'])]
    public function projectMappings(string $id): JsonResponse
    {
        $project = $this->findProject($id);
        if ($project === null) {
            return $this->json(['error' => 'Project not found'], Response::HTTP_NOT_FOUND);
        }

        /** @var OntologyMapping[] $mappings */
        $mappings = $this->em->getRepository(OntologyMapping::class)->findBy(['project' => $project]);

        $summary = [
            'total' => count($mappings),
            'approved' => 0,
            'suggested' => 0,
            'unmapped' => 0,
            'rejected' => 0,
            'mandatory_unresolved' => 0,
        ];

        foreach ($mappings as $m) {
            switch ($m->getMappingStatus()) {
                case OntologyMapping::STATUS_APPROVED:
                    $summary['approved']++;
                    break;
                case OntologyMapping::STATUS_SUGGESTED:
                    $summary['suggested']++;
                    break;
                case OntologyMapping::STATUS_REJECTED:
                    $summary['rejected']++;
                    break;
                case OntologyMapping::STATUS_UNMAPPED:
                default:
                    $summary['unmapped']++;
                    break;
            }
            if ($m->isMandatory() && $m->getMappingStatus() !== OntologyMapping::STATUS_APPROVED) {
                $summary['mandatory_unresolved']++;
            }
        }

        return $this->json([
            'mappings' => array_map($this->serializeMapping(...), $mappings),
            'summary' => $summary,
        ]);
    }

    /** Case-insensitive label/synonym match against seeded terms. */
    private function suggestTerm(string $sourceValue): ?OntologyTerm
    {
        $needle = strtolower(trim($sourceValue));
        if ($needle === '') {
            return null;
        }

        /** @var OntologyTerm[] $terms */
        $terms = $this->em->getRepository(OntologyTerm::class)->findAll();
        foreach ($terms as $term) {
            if (strtolower(trim($term->getLabel())) === $needle) {
                return $term;
            }
            foreach ($term->getSynonyms() as $syn) {
                if (strtolower(trim((string) $syn)) === $needle) {
                    return $term;
                }
            }
        }

        return null;
    }

    /** @return array<string,mixed> */
    private function serializeTerm(OntologyTerm $t): array
    {
        return [
            'id' => $t->getId()->toRfc4122(),
            'label' => $t->getLabel(),
            'ontology_prefix' => $t->getOntologyPrefix(),
            'curie' => $t->getCurie(),
            'iri' => $t->getIri(),
            'definition' => $t->getDefinition(),
            'synonyms' => $t->getSynonyms(),
            'source' => $t->getSource(),
            'term_version' => $t->getTermVersion(),
        ];
    }

    /** @return array<string,mixed> */
    private function serializeMapping(OntologyMapping $m): array
    {
        $term = $m->getOntologyTerm();

        return [
            'id' => $m->getId()->toRfc4122(),
            'project_id' => $m->getProject()->getId()->toRfc4122(),
            'source_entity_type' => $m->getSourceEntityType(),
            'source_field' => $m->getSourceField(),
            'source_value' => $m->getSourceValue(),
            'mapping_status' => $m->getMappingStatus(),
            'mapping_confidence' => $m->getMappingConfidence(),
            'mandatory' => $m->isMandatory(),
            'suggested_term' => $term === null ? null : [
                'curie' => $term->getCurie(),
                'label' => $term->getLabel(),
            ],
            'reviewer_note' => $m->getReviewerNote(),
            'reviewed_by' => $m->getReviewedBy(),
        ];
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

    private function findMapping(string $id): ?OntologyMapping
    {
        try {
            return $this->em->find(OntologyMapping::class, Ulid::fromString($id)->toRfc4122());
        } catch (\Throwable) {
            return null;
        }
    }
}
