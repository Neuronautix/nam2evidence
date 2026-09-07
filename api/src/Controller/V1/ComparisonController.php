<?php

declare(strict_types=1);

namespace App\Controller\V1;

use App\Entity\ContextOfUseCard;
use App\Entity\EvidenceItem;
use App\Entity\NamCore\EvidenceAssessment;
use App\Entity\NAMStudy;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/** Cross-project comparison by Context of Use; deliberately not a leaderboard. */
#[Route('/api/v1/compare')]
final class ComparisonController extends AbstractController
{
    private const EVIDENCE_DOMAINS = [
        'analytical_validity', 'technical_reproducibility', 'biological_relevance',
        'reference_compound_performance', 'exposure_relevance', 'data_integrity',
        'limitation_analysis', 'regulatory_alignment',
    ];

    public function __construct(private readonly EntityManagerInterface $em) {}

    #[Route('/contexts', name: 'api_v1_compare_contexts', methods: ['GET'])]
    public function contexts(Request $request): JsonResponse
    {
        $filters = [
            'q' => trim((string) $request->query->get('q', '')),
            'biological_domain' => trim((string) $request->query->get('biological_domain', '')),
            'endpoint_class' => trim((string) $request->query->get('endpoint_class', '')),
            'intended_use' => trim((string) $request->query->get('intended_use', '')),
            'method_type' => trim((string) $request->query->get('method_type', '')),
            'project_type' => trim((string) $request->query->get('project_type', '')),
            'regulatory_authority' => trim((string) $request->query->get('regulatory_authority', '')),
        ];

        /** @var ContextOfUseCard[] $contexts */
        $contexts = $this->em->getRepository(ContextOfUseCard::class)->findAll();
        $rows = [];
        foreach ($contexts as $cou) {
            if (!$this->matches($cou, $filters)) continue;
            $project = $cou->getProject(); $method = $cou->getNamMethod(); $sourceProject = $method?->getSourceProject();
            /** @var NAMStudy[] $studies */
            $studies = $this->em->getRepository(NAMStudy::class)->findBy(['contextOfUse' => $cou]);
            $statusCounts = ['met' => 0, 'partial' => 0, 'not_met' => 0, 'not_applicable' => 0];
            $domainEvidence = [];
            foreach (self::EVIDENCE_DOMAINS as $domain) $domainEvidence[$domain] = ['met' => 0, 'partial' => 0, 'not_met' => 0, 'not_applicable' => 0, 'total' => 0];
            $evidenceCount = 0;
            foreach ($studies as $study) {
                /** @var EvidenceItem[] $items */
                $items = $this->em->getRepository(EvidenceItem::class)->findBy(['study' => $study]);
                foreach ($items as $item) {
                    $evidenceCount++; $status = $item->getStatus(); $domain = $item->getDomain();
                    if (array_key_exists($status, $statusCounts)) $statusCounts[$status]++;
                    if (!isset($domainEvidence[$domain])) $domainEvidence[$domain] = ['met' => 0, 'partial' => 0, 'not_met' => 0, 'not_applicable' => 0, 'total' => 0];
                    if (array_key_exists($status, $domainEvidence[$domain])) $domainEvidence[$domain][$status]++;
                    $domainEvidence[$domain]['total']++;
                }
            }
            $coveredDomains = count(array_filter($domainEvidence, static fn(array $counts): bool => $counts['total'] > 0));

            /** @var EvidenceAssessment[] $assessments */
            $assessments = $this->em->getRepository(EvidenceAssessment::class)->findBy(['contextOfUse' => $cou]);
            if ($method !== null) {
                $assessments = array_merge($assessments, $this->em->getRepository(EvidenceAssessment::class)->findBy(['namMethod' => $method, 'contextOfUse' => null]));
            }
            $assessmentRows = array_map(static fn(EvidenceAssessment $a): array => [
                'assessment_type' => $a->getAssessmentType(), 'assessor_organization' => $a->getAssessorOrganization(),
                'authority' => $a->getAuthority(), 'status' => $a->getStatus(), 'conclusion' => $a->getConclusion(),
                'assessed_at' => $a->getAssessedAt()?->format('Y-m-d'), 'source_url' => $a->getSourceUrl(), 'conditions' => $a->getConditions(),
            ], $assessments);

            $signatureMaterial = implode('|', [$this->normaliseText($cou->getBiologicalDomain()), $this->normaliseText($cou->getEndpointClass()), $this->normaliseText($cou->getIntendedUse()), $this->normaliseText($cou->getDecisionSupported())]);
            $rows[] = [
                'project' => ['id' => (string) $project->getId(), 'name' => $project->getName(), 'project_type' => $project->getProjectType(), 'sponsor' => $project->getSponsor(), 'drug_name' => $project->getDrugName(), 'source_name' => $project->getSourceName(), 'external_id' => $project->getExternalId(), 'source_url' => $project->getSourceUrl()],
                'method' => $method !== null ? ['entity_id' => (string) $method->getId(), 'method_id' => $method->getMethodId(), 'name' => $method->getLabel(), 'method_type' => $method->getMethodType(), 'developer' => $method->getDeveloper(), 'method_version' => $method->getMethodVersion(), 'maturity_status' => $method->getMaturityStatus()] : ['entity_id' => null, 'method_id' => null, 'name' => null, 'method_type' => $cou->getNamType(), 'developer' => null, 'method_version' => null, 'maturity_status' => 'unknown'],
                'source_project' => $sourceProject !== null ? ['source_type' => $sourceProject->getSourceType(), 'label' => $sourceProject->getLabel(), 'external_id' => $sourceProject->getExternalId(), 'organisation' => $sourceProject->getOrganisation(), 'source_url' => $sourceProject->getSourceUrl()] : null,
                'context_of_use' => ['cou_id' => $cou->getCouId(), 'regulatory_question' => $cou->getRegulatoryQuestion(), 'intended_use' => $cou->getIntendedUse(), 'decision_supported' => $cou->getDecisionSupported(), 'biological_domain' => $cou->getBiologicalDomain(), 'endpoint_class' => $cou->getEndpointClass(), 'population_relevance' => $cou->getPopulationRelevance(), 'test_article_scope' => $cou->getTestArticleScope(), 'applicability_domain' => $cou->getApplicabilityDomain(), 'regulatory_authority' => $cou->getRegulatoryAuthority(), 'limitations' => $cou->getLimitations(), 'acceptance_criteria' => $cou->getAcceptanceCriteria(), 'support_level' => $cou->getRegulatoryConfidenceLevel(), 'source_text' => $cou->getSourceText(), 'source_reference' => $cou->getSourceReference(), 'context_signature' => hash('sha256', $signatureMaterial)],
                'evidence_profile' => ['study_count' => count($studies), 'evidence_item_count' => $evidenceCount, 'covered_domains' => $coveredDomains, 'total_domains' => count(self::EVIDENCE_DOMAINS), 'status_counts' => $statusCounts, 'by_domain' => $domainEvidence],
                'assessments' => $assessmentRows,
            ];
        }

        usort($rows, static function (array $a, array $b): int {
            $domain = strcasecmp((string) $a['context_of_use']['biological_domain'], (string) $b['context_of_use']['biological_domain']);
            return $domain !== 0 ? $domain : strcasecmp((string) ($a['method']['name'] ?? $a['method']['method_type']), (string) ($b['method']['name'] ?? $b['method']['method_type']));
        });

        return $this->json(['count' => count($rows), 'filters' => array_filter($filters, static fn(string $v): bool => $v !== ''), 'facets' => $this->facets($rows), 'rows' => $rows, 'interpretation_note' => 'Evidence profiles are descriptive. nam2evidence does not convert them into a single validity or regulatory-acceptance score.']);
    }

    /** @param array<string,string> $filters */
    private function matches(ContextOfUseCard $cou, array $filters): bool
    {
        $project = $cou->getProject(); $method = $cou->getNamMethod();
        if ($filters['biological_domain'] !== '' && !$this->contains($cou->getBiologicalDomain(), $filters['biological_domain'])) return false;
        if ($filters['endpoint_class'] !== '' && !$this->contains($cou->getEndpointClass(), $filters['endpoint_class'])) return false;
        if ($filters['intended_use'] !== '' && !$this->contains($cou->getIntendedUse(), $filters['intended_use'])) return false;
        if ($filters['method_type'] !== '' && !$this->contains($method?->getMethodType() ?? $cou->getNamType(), $filters['method_type'])) return false;
        if ($filters['project_type'] !== '' && strcasecmp($project->getProjectType(), $filters['project_type']) !== 0) return false;
        if ($filters['regulatory_authority'] !== '' && !$this->contains($cou->getRegulatoryAuthority() ?? '', $filters['regulatory_authority'])) return false;
        if ($filters['q'] !== '') {
            $haystack = implode(' ', [$project->getName(), $project->getSourceName() ?? '', $method?->getLabel() ?? '', $method?->getDeveloper() ?? '', $cou->getRegulatoryQuestion(), $cou->getIntendedUse(), $cou->getDecisionSupported(), $cou->getBiologicalDomain(), $cou->getEndpointClass()]);
            if (!$this->contains($haystack, $filters['q'])) return false;
        }
        return true;
    }

    private function contains(string $haystack, string $needle): bool { return str_contains(strtolower($haystack), strtolower($needle)); }
    private function normaliseText(string $value): string { return preg_replace('/\s+/', ' ', trim(strtolower($value))) ?? ''; }

    /** @param list<array<string,mixed>> $rows */
    private function facets(array $rows): array
    {
        $facets = ['method_types' => [], 'biological_domains' => [], 'regulatory_authorities' => [], 'project_types' => []];
        foreach ($rows as $row) {
            $facets['method_types'][] = (string) ($row['method']['method_type'] ?? '');
            $facets['biological_domains'][] = (string) ($row['context_of_use']['biological_domain'] ?? '');
            $facets['regulatory_authorities'][] = (string) ($row['context_of_use']['regulatory_authority'] ?? '');
            $facets['project_types'][] = (string) ($row['project']['project_type'] ?? '');
        }
        foreach ($facets as $key => $values) {
            $values = array_values(array_unique(array_filter($values, static fn(string $v): bool => $v !== ''))); sort($values, SORT_NATURAL | SORT_FLAG_CASE); $facets[$key] = $values;
        }
        return $facets;
    }
}
