<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller;

use App\Entity\ClaimNode;
use App\Entity\ContextOfUseCard;
use App\Entity\NamCore\OntologyMapping;
use App\Entity\NamCore\OntologyTerm;
use App\Entity\NAMStudy;
use App\Entity\Project;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * End-to-end coverage of the NAM-CORE v1 API: endpoint import, ontology mapping
 * approval/rejection, semantic validation, readiness, exports, and the review gate.
 */
final class NamCoreApiTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->purge();
    }

    public function testEndpointImportPreviewThenImport(): void
    {
        $project = $this->createProject();
        $id = (string) $project->getId();

        // Preview (no mapping)
        $csv = "endpoint,value,unit,timepoint,timepoint_unit\natp_viability,98.2,percent,24,hour\nldh_release,12.1,,24,hour\n";
        $this->client->jsonRequest('POST', "/api/v1/projects/$id/endpoint-measurements/import", ['csv' => $csv]);
        self::assertResponseIsSuccessful();
        $preview = $this->json();
        self::assertSame('preview', $preview['mode']);
        self::assertSame('endpoint_id', $preview['preview']['suggested_mapping']['endpoint']);

        // Import with the suggested mapping
        $mapping = $preview['preview']['suggested_mapping'];
        $this->client->jsonRequest('POST', "/api/v1/projects/$id/endpoint-measurements/import", ['csv' => $csv, 'mapping' => $mapping]);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $summary = $this->json()['summary'];
        self::assertSame(2, $summary['imported']);
        // The row with an empty unit should raise a warning, not block the import.
        self::assertGreaterThanOrEqual(1, $summary['warning_count']);

        // Listing returns the stored canonical measurements
        $this->client->request('GET', "/api/v1/projects/$id/endpoint-measurements");
        self::assertResponseIsSuccessful();
        self::assertSame(2, $this->json()['count']);
    }

    public function testOntologyMapApproveAndReject(): void
    {
        $project = $this->createProject();
        $id = (string) $project->getId();

        $term = (new OntologyTerm())->setLabel('hepatocyte-like cell')->setOntologyPrefix('CL')->setCurie('CL:0002310');
        $this->em->persist($term);
        $this->em->flush();

        // Auto-suggest against the seeded term
        $this->client->jsonRequest('POST', '/api/v1/ontology/map', [
            'project_id' => $id, 'source_entity_type' => 'cell_type', 'source_value' => 'hepatocyte-like cell', 'mandatory' => true,
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $mapping = $this->json();
        self::assertSame('suggested', $mapping['mapping_status']);
        $mid = $mapping['id'];

        // Approve
        $this->client->request('PATCH', "/api/v1/ontology/mappings/$mid/approve", server: ['CONTENT_TYPE' => 'application/json'], content: json_encode(['reviewed_by' => 'tester']));
        self::assertResponseIsSuccessful();
        self::assertSame('approved', $this->json()['mapping_status']);

        // A second, rejectable mapping
        $this->client->jsonRequest('POST', '/api/v1/ontology/map', [
            'project_id' => $id, 'source_entity_type' => 'endpoint', 'source_value' => 'unknown thing',
        ]);
        $mid2 = $this->json()['id'];
        $this->client->request('PATCH', "/api/v1/ontology/mappings/$mid2/reject", server: ['CONTENT_TYPE' => 'application/json'], content: json_encode(['reviewer_note' => 'not a real endpoint']));
        self::assertResponseIsSuccessful();
        self::assertSame('rejected', $this->json()['mapping_status']);
    }

    public function testSemanticValidationAndReadinessAndGate(): void
    {
        $project = $this->createProject(withPendingClaim: true);
        $id = (string) $project->getId();

        $this->client->request('GET', "/api/v1/projects/$id/semantic-validation");
        self::assertResponseIsSuccessful();
        $report = $this->json();
        self::assertArrayHasKey('conforms', $report);
        self::assertArrayHasKey('issues', $report);
        // A pending claim is a blocking issue.
        self::assertFalse($report['conforms']);

        $this->client->request('GET', "/api/v1/projects/$id/readiness-report");
        self::assertResponseIsSuccessful();
        $readiness = $this->json();
        self::assertCount(10, $readiness['dimensions']);
        self::assertSame(20, $readiness['max_score']);
        self::assertStringContainsString('POC', $readiness['label']);

        $this->client->request('GET', "/api/v1/projects/$id/export-gate");
        self::assertResponseIsSuccessful();
        $gate = $this->json();
        self::assertTrue($gate['blocked']);
        self::assertGreaterThan(0, $gate['blocker_count']);
    }

    public function testExportsReturnArtifacts(): void
    {
        $project = $this->createProject();
        $id = (string) $project->getId();

        $this->client->request('GET', "/api/v1/projects/$id/exports/jsonld");
        self::assertResponseIsSuccessful();
        $doc = json_decode((string) $this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('EvidencePackage', $doc['@type']);
        self::assertArrayHasKey('@graph', $doc);

        $this->client->request('GET', "/api/v1/projects/$id/exports/turtle");
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('@prefix nam:', (string) $this->client->getResponse()->getContent());

        foreach (['isa-tab', 'parquet', 'ro-crate'] as $fmt) {
            $this->client->request('GET', "/api/v1/projects/$id/exports/$fmt");
            self::assertResponseIsSuccessful();
            self::assertStringStartsWith('PK', (string) $this->client->getResponse()->getContent(), "$fmt should be a ZIP");
        }

        // Audit log recorded the exports
        $this->client->request('GET', "/api/v1/projects/$id/audit-log");
        self::assertResponseIsSuccessful();
        self::assertGreaterThanOrEqual(1, $this->json()['count']);
    }

    // ── fixtures ────────────────────────────────────────────────────────────

    private function createProject(bool $withPendingClaim = false): Project
    {
        $project = (new Project())->setName('Test project')->setDrugName('TestDrug');
        $this->em->persist($project);

        $cou = (new ContextOfUseCard())->setCouId('COU-TEST-' . uniqid())->setProject($project)
            ->setNamType('Organoid')->setRegulatoryQuestion('Does it?')->setIntendedUse('characterise')
            ->setDecisionSupported('dose')->setBiologicalDomain('hepatotoxicity')->setEndpointClass('cytotoxicity')
            ->setRegulatoryConfidenceLevel('supportive');
        $this->em->persist($cou);

        $study = (new NAMStudy())->setStudyId('NAM-TEST-' . uniqid())->setProject($project)
            ->setContextOfUse($cou)->setTitle('Test study');
        $this->em->persist($study);

        if ($withPendingClaim) {
            $claim = (new ClaimNode())->setClaimId('CLAIM-TEST-' . uniqid())->setProject($project)
                ->setClaimText('A pending claim')->setNodeType('claim')->setClaimType('empirical')
                ->setContextOfUse($cou)->setConfidence('decision_informing')->setReviewStatus('human_review_required');
            $this->em->persist($claim);
        }

        $this->em->flush();
        return $project;
    }

    /** @return array<string,mixed> */
    private function json(): array
    {
        return json_decode((string) $this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
    }

    private function purge(): void
    {
        $conn = $this->em->getConnection();
        $conn->executeStatement('TRUNCATE TABLE namcore_audit_log, namcore_endpoint_measurement, namcore_ontology_mapping, namcore_ontology_term, ectd_mappings, claim_edges, claim_nodes, evidence_items, nam_studies, context_of_use_cards, export_packages, projects RESTART IDENTITY CASCADE');
    }
}
