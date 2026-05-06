<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller;

use App\Entity\ClaimNode;
use App\Entity\ContextOfUseCard;
use App\Entity\EvidenceItem;
use App\Entity\ExportPackage;
use App\Entity\NAMStudy;
use App\Entity\Project;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
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

    #[DataProvider('downloadFormatProvider')]
    public function testExportDownloadReturnsExpectedArtifactForEachFormat(
        string $format,
        string $expectedContentType,
        string $expectedFileSuffix,
        string $expectedBodyFragment
    ): void {
        [$project] = $this->createApprovedProjectWithStudyAndEvidence();

        $this->client->request('POST', sprintf('/api/projects/%s/export/download?format=%s', (string) $project->getId(), $format));

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        self::assertStringContainsString(
            $expectedContentType,
            (string) $this->client->getResponse()->headers->get('Content-Type')
        );
        self::assertStringContainsString(
            $expectedFileSuffix,
            (string) $this->client->getResponse()->headers->get('Content-Disposition')
        );
        self::assertStringContainsString(
            $expectedBodyFragment,
            (string) $this->client->getResponse()->getContent()
        );

        $packages = $this->em->getRepository(ExportPackage::class)->findBy(['project' => $project]);
        self::assertCount(1, $packages, 'Successful export download should persist exactly one export package.');
        self::assertSame($project->getId()->toBase32(), $packages[0]->getProject()->getId()->toBase32());
    }

    public static function downloadFormatProvider(): iterable
    {
        yield 'json artifact' => ['json', 'application/json', '.json', '"package_id"'];
        yield 'csv artifact' => ['csv', 'text/csv', '.csv', 'EVID-TEST-001'];
        yield 'markdown artifact' => ['md', 'text/markdown', '.md', '# NAM Evidence Dossier'];
        yield 'text artifact' => ['txt', 'text/plain', '.txt', 'eCTD Module 4 Folder Map'];
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

    /** @return array{0: Project, 1: ClaimNode, 2: NAMStudy, 3: EvidenceItem} */
    private function createApprovedProjectWithStudyAndEvidence(): array
    {
        $project = (new Project())
            ->setName('Export Download Test Project')
            ->setDrugName('NX-5522')
            ->setReviewStatus('approved');

        $cou = (new ContextOfUseCard())
            ->setCouId('COU-TEST-002')
            ->setProject($project)
            ->setNamType('Organoid')
            ->setRegulatoryQuestion('Does the NAM support export generation?')
            ->setDrugDevelopmentStage('ind_enabling')
            ->setIntendedUse('Generate a complete evidence package')
            ->setDecisionSupported('Internal regulatory package readiness')
            ->setBiologicalDomain('Hepatic')
            ->setEndpointClass('Hepatotoxicity')
            ->setPopulationRelevance('Adult donors')
            ->setLimitations(['Model requires qualified reviewer sign-off.'])
            ->setAcceptanceCriteria(['All claims approved'])
            ->setRegulatoryConfidenceLevel('supportive');

        $study = (new NAMStudy())
            ->setStudyId('NAM-STUDY-TEST-001')
            ->setProject($project)
            ->setContextOfUse($cou)
            ->setTitle('Liver organoid export test study')
            ->setModelSystem([
                'namo_class' => 'Organoid',
                'cell_type' => 'iPSC-derived hepatocyte',
                'species' => 'human',
                'tissue_origin' => 'liver',
            ])
            ->setExperimentalDesign(['replicates' => 3])
            ->setAssayMetadata(['instrument' => 'High-content imaging'])
            ->setDataOutputs(['tc50_uM' => 12.4])
            ->setProvenance(['eln' => 'ELN-TEST-001']);

        $evidence = (new EvidenceItem())
            ->setEvidenceId('EVID-TEST-001')
            ->setStudy($study)
            ->setDomain('biological_relevance')
            ->setQuestion('Does the model reproduce the expected hepatotoxic phenotype?')
            ->setEvidenceType('benchmark compound panel')
            ->setStatus('met')
            ->setNotes('Benchmark compounds reproduced expected toxicity ranking.')
            ->setSupportingData('See concentration-response summary.');

        $claim = (new ClaimNode())
            ->setClaimId('CLAIM-TEST-APPROVED-001')
            ->setProject($project)
            ->setClaimText('The liver organoid NAM supports hepatotoxicity triage decisions.')
            ->setClaimType('empirical')
            ->setContextOfUse($cou)
            ->setConfidence('decision_informing')
            ->setSupportingEvidence(['EVID-TEST-001'])
            ->setContradictoryEvidence([])
            ->setLimitations(['Requires reviewer confirmation before submission.'])
            ->setEctdTargetSections(['4.2.3.7.3'])
            ->setReviewStatus('approved');

        $this->em->persist($project);
        $this->em->persist($cou);
        $this->em->persist($study);
        $this->em->persist($evidence);
        $this->em->persist($claim);
        $this->em->flush();

        return [$project, $claim, $study, $evidence];
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
