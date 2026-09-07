<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class IntegrationComparisonApiTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->em->getConnection()->executeStatement('TRUNCATE TABLE projects RESTART IDENTITY CASCADE');
    }

    public function testNormalizedImportsBecomeCrossProjectComparable(): void
    {
        $this->client->jsonRequest('POST', '/api/v1/integrations/normalized', $this->payload('External liver organoid programme', 'academic', 'LIVER-ORG-001', 'Human liver organoid assay', 'organoid', 'COU-EXT-ORG-001', 'academic', null));
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        self::assertSame(2, $this->json()['evidence_count']);

        $this->client->jsonRequest('POST', '/api/v1/integrations/normalized', $this->payload('Regulatory liver-chip record', 'regulatory_reference', 'LIVER-CHIP-001', 'Human liver microphysiological system', 'organ_on_chip', 'COU-EXT-CHIP-001', 'fda', 'FDA'));
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $this->client->request('GET', '/api/v1/compare/contexts?biological_domain=DILI');
        self::assertResponseIsSuccessful();
        $comparison = $this->json();
        self::assertSame(2, $comparison['count']);
        self::assertContains('organ_on_chip', $comparison['facets']['method_types']);
        self::assertContains('organoid', $comparison['facets']['method_types']);
        self::assertStringContainsString('does not convert', $comparison['interpretation_note']);

        $byMethod = [];
        foreach ($comparison['rows'] as $row) $byMethod[$row['method']['method_id']] = $row;
        self::assertSame(2, $byMethod['LIVER-ORG-001']['evidence_profile']['covered_domains']);
        self::assertSame(1, $byMethod['LIVER-ORG-001']['evidence_profile']['status_counts']['met']);
        self::assertSame(1, $byMethod['LIVER-ORG-001']['evidence_profile']['status_counts']['partial']);
        self::assertSame('FDA', $byMethod['LIVER-CHIP-001']['context_of_use']['regulatory_authority']);
        self::assertSame('accepted', $byMethod['LIVER-CHIP-001']['assessments'][0]['status']);

        $this->client->request('GET', '/api/v1/compare/contexts?method_type=organ_on_chip');
        self::assertResponseIsSuccessful();
        self::assertSame(1, $this->json()['count']);
    }

    /** @return array<string,mixed> */
    private function payload(string $projectName, string $projectType, string $methodId, string $methodName, string $methodType, string $couId, string $sourceType, ?string $authority): array
    {
        return [
            'project' => ['name' => $projectName, 'project_type' => $projectType, 'description' => 'Public/external NAM project normalized for comparison.', 'source_name' => strtoupper($sourceType), 'external_id' => 'EXT-' . $methodId, 'source_url' => 'https://example.org/' . strtolower($methodId)],
            'source_project' => ['label' => $projectName . ' source record', 'source_type' => $sourceType, 'organisation' => $authority ?? 'Example research organisation', 'external_id' => 'EXT-' . $methodId, 'source_url' => 'https://example.org/' . strtolower($methodId)],
            'method' => ['method_id' => $methodId, 'name' => $methodName, 'method_type' => $methodType, 'developer' => 'Example developer', 'maturity_status' => 'validation'],
            'contexts_of_use' => [[
                'cou_id' => $couId, 'nam_type' => $methodType,
                'regulatory_question' => 'Can this method inform drug-induced liver injury risk?',
                'intended_use' => 'DILI hazard identification and risk characterization',
                'decision_supported' => 'Prioritize compounds for further liver safety assessment',
                'biological_domain' => 'Drug-induced liver injury (DILI)', 'endpoint_class' => 'hepatocellular injury',
                'test_article_scope' => 'small-molecule pharmaceuticals',
                'applicability_domain' => ['modality' => ['small_molecule'], 'species_relevance' => ['human']],
                'regulatory_authority' => $authority, 'source_text' => 'Source CoU text retained verbatim for normalization audit.',
                'source_reference' => 'https://example.org/cou/' . strtolower($couId), 'support_level' => 'supportive',
            ]],
            'studies' => [['study_id' => 'STUDY-' . $methodId, 'cou_id' => $couId, 'title' => $methodName . ' validation study', 'model_system' => ['species' => 'human']]],
            'evidence' => [
                ['evidence_id' => 'EV-A-' . $methodId, 'study_id' => 'STUDY-' . $methodId, 'domain' => 'biological_relevance', 'question' => 'Is the model biologically relevant to the CoU?', 'evidence_type' => 'validation_study', 'status' => 'met'],
                ['evidence_id' => 'EV-B-' . $methodId, 'study_id' => 'STUDY-' . $methodId, 'domain' => 'technical_reproducibility', 'question' => 'Is technical reproducibility sufficiently characterized?', 'evidence_type' => 'interlaboratory_study', 'status' => 'partial'],
            ],
            'assessments' => [[
                'cou_id' => $couId, 'assessment_type' => $authority !== null ? 'regulatory_review' : 'scientific_validation',
                'assessor_organization' => $authority ?? 'Example validation consortium', 'authority' => $authority,
                'status' => $authority !== null ? 'accepted' : 'informative',
                'conclusion' => 'Assessment status applies only to this specified Context of Use.',
                'source_url' => 'https://example.org/assessment/' . strtolower($methodId),
            ]],
        ];
    }

    /** @return array<string,mixed> */
    private function json(): array
    {
        return json_decode((string) $this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
    }
}
