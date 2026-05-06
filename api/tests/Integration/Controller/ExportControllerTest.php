<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller;

use App\Entity\ClaimNode;
use App\Entity\ContextOfUseCard;
use App\Entity\ExportPackage;
use App\Entity\Project;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class ExportControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        $this->purgeDatabase();
    }

    public function testExportIsBlockedWhenPendingClaimsExist(): void
    {
        [$project, $claim] = $this->createProjectWithPendingClaim();

        $this->client->request('POST', '/api/projects/' . (string) $project->getId() . '/export');

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);

        $data = json_decode((string) $this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('Export blocked: human review required', $data['error'] ?? null);
        self::assertIsArray($data['pending_ids'] ?? null);
        self::assertContains($claim->getClaimId(), $data['pending_ids']);

        $packages = $this->em->getRepository(ExportPackage::class)->findBy(['project' => $project]);
        self::assertCount(0, $packages, 'Export should not persist a package while claims are pending review.');
    }

    /** @return array{0: Project, 1: ClaimNode} */
    private function createProjectWithPendingClaim(): array
    {
        $project = (new Project())
            ->setName('Export Gate Test Project')
            ->setDrugName('NX-4471')
            ->setReviewStatus('pending');

        $cou = (new ContextOfUseCard())
            ->setCouId('COU-TEST-001')
            ->setProject($project)
            ->setNamType('Organoid')
            ->setRegulatoryQuestion('Does the NAM support hepatotoxicity triage?')
            ->setDrugDevelopmentStage('ind_enabling')
            ->setIntendedUse('Internal safety signal evaluation')
            ->setDecisionSupported('Progress to IND-enabling package')
            ->setBiologicalDomain('Hepatic')
            ->setEndpointClass('Hepatotoxicity')
            ->setPopulationRelevance('Adult reference donors')
            ->setLimitations([])
            ->setAcceptanceCriteria([])
            ->setRegulatoryConfidenceLevel('exploratory');

        $claim = (new ClaimNode())
            ->setClaimId('CLAIM-TEST-001')
            ->setProject($project)
            ->setClaimText('Primary NAM readout indicates hepatotoxic risk.')
            ->setClaimType('empirical')
            ->setContextOfUse($cou)
            ->setConfidence('supportive')
            ->setSupportingEvidence([])
            ->setContradictoryEvidence([])
            ->setLimitations([])
            ->setEctdTargetSections([])
            ->setReviewStatus('human_review_required');

        $this->em->persist($project);
        $this->em->persist($cou);
        $this->em->persist($claim);
        $this->em->flush();

        return [$project, $claim];
    }

    private function purgeDatabase(): void
    {
        $connection = $this->em->getConnection();
        $connection->executeStatement('DELETE FROM export_packages');
        $connection->executeStatement('DELETE FROM claim_edges');
        $connection->executeStatement('DELETE FROM ectd_mappings');
        $connection->executeStatement('DELETE FROM evidence_items');
        $connection->executeStatement('DELETE FROM claim_nodes');
        $connection->executeStatement('DELETE FROM nam_studies');
        $connection->executeStatement('DELETE FROM context_of_use_cards');
        $connection->executeStatement('DELETE FROM projects');
    }
}
