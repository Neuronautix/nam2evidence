<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller;

use App\Entity\ClaimNode;
use App\Entity\ContextOfUseCard;
use App\Entity\EvidenceItem;
use App\Entity\NamCore\ExternalStatement;
use App\Entity\NamCore\ExternalStatementLink;
use App\Entity\NamCore\GuidanceProfile;
use App\Entity\NamCore\GuidanceRequirement;
use App\Entity\NamCore\RequirementAssessment;
use App\Entity\NAMStudy;
use App\Entity\Project;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * External statements (Knowledge Loom shaped) are captured immutably, linked
 * only by human decision, never count as validation evidence, and never leak
 * restricted content into exports.
 */
final class ExternalStatementApiTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->em->getConnection()->executeStatement('TRUNCATE TABLE namcore_guidance_profile, projects RESTART IDENTITY CASCADE');
    }

    public function testCaptureIsIdempotentAndFailsClosedOnChangedBytes(): void
    {
        [$project] = $this->fixture('A');
        $pid = $project->getId()->toRfc4122();

        $this->client->jsonRequest('POST', "/api/v1/projects/$pid/external-statements/import", $this->capturePayload());
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $first = $this->json();
        self::assertSame(2, $first['created_count']);
        self::assertSame('captured_unverified', $first['statements'][0]['capture_status']);
        self::assertFalse($first['statements'][0]['eligible_for_support']);
        $hash = $first['statements'][0]['payload_sha256'];

        // Byte-identical replay: nothing new, HTTP 200.
        $this->client->jsonRequest('POST', "/api/v1/projects/$pid/external-statements/import", $this->capturePayload());
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        self::assertSame('replayed', $this->json()['status']);
        self::assertSame($hash, $this->json()['statements'][0]['payload_sha256']);

        // Same identity, different text: conflict, and NOTHING is written (the new third statement is not created either).
        $changed = $this->capturePayload();
        $changed['statements'][0]['text'] = 'A silently edited statement.';
        $changed['statements'][] = ['statement_uri' => 'https://doi.org/10.5555/test.s3', 'text' => 'Third statement.'];
        $this->client->jsonRequest('POST', "/api/v1/projects/$pid/external-statements/import", $changed);
        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        self::assertCount(2, $this->em->getRepository(ExternalStatement::class)->findBy(['project' => $project]));

        // Capture without an attributable actor is refused.
        $anonymous = $this->capturePayload();
        unset($anonymous['captured_by']);
        $this->client->jsonRequest('POST', "/api/v1/projects/$pid/external-statements/import", $anonymous);
        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);

        $this->client->request('GET', "/api/v1/projects/$pid/audit-log");
        self::assertResponseIsSuccessful();
        self::assertContains('import', array_column($this->json()['entries'], 'action'));
    }

    public function testLinksRequireHumanDecisionAndSupportRequiresVerifiedPublicStatement(): void
    {
        [$project, , $evidence, $claim, $assessment] = $this->fixture('B');
        $pid = $project->getId()->toRfc4122();
        $this->client->jsonRequest('POST', "/api/v1/projects/$pid/external-statements/import", $this->capturePayload());
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $publicStatement = $this->json()['statements'][0];
        $restrictedStatement = $this->json()['statements'][1];

        // A suggestion is created without a reviewer and counts for nothing.
        $this->client->jsonRequest('POST', "/api/v1/projects/$pid/external-statement-links", [
            'external_statement_id' => $publicStatement['id'],
            'target_type' => 'requirement_assessment',
            'target_id' => $assessment->getId()->toRfc4122(),
            'relationship' => 'supports',
            'link_origin' => 'deterministic_rule',
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $link = $this->json();
        self::assertSame('suggested', $link['review_status']);
        self::assertFalse($link['counts_as_evidence']);

        // Approval without a reviewer identity is refused.
        $this->client->request('PATCH', "/api/v1/projects/$pid/external-statement-links/{$link['id']}/approve", server: ['CONTENT_TYPE' => 'application/json'], content: '{}');
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);

        // Approval as *supports* before the capture was verified is refused.
        $this->client->request('PATCH', "/api/v1/projects/$pid/external-statement-links/{$link['id']}/approve", server: ['CONTENT_TYPE' => 'application/json'], content: json_encode(['reviewed_by' => 'qualified-reviewer']));
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);

        // Verify the capture (human), then approval succeeds and is audited.
        $this->client->request('PATCH', "/api/v1/projects/$pid/external-statements/{$publicStatement['id']}/verify", server: ['CONTENT_TYPE' => 'application/json'], content: json_encode(['reviewed_by' => 'qualified-reviewer']));
        self::assertResponseIsSuccessful();
        self::assertTrue($this->json()['eligible_for_support']);

        $this->client->request('PATCH', "/api/v1/projects/$pid/external-statement-links/{$link['id']}/approve", server: ['CONTENT_TYPE' => 'application/json'], content: json_encode(['reviewed_by' => 'qualified-reviewer', 'reviewer_note' => 'Same CoU, independent donors.']));
        self::assertResponseIsSuccessful();
        self::assertSame('approved', $this->json()['review_status']);
        self::assertSame('qualified-reviewer', $this->json()['reviewed_by']);

        // A restricted statement can never be approved as supporting evidence, even by a human...
        $this->client->jsonRequest('POST', "/api/v1/projects/$pid/external-statement-links", [
            'external_statement_id' => $restrictedStatement['id'],
            'target_type' => 'evidence_item',
            'target_id' => $evidence->getId()->toRfc4122(),
            'relationship' => 'supports',
            'review_status' => 'approved',
            'reviewed_by' => 'qualified-reviewer',
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        // ...but it may contextualize, and a reviewer may record that a statement is not comparable.
        $this->client->jsonRequest('POST', "/api/v1/projects/$pid/external-statement-links", [
            'external_statement_id' => $restrictedStatement['id'],
            'target_type' => 'claim',
            'target_id' => $claim->getClaimId(),
            'relationship' => 'contextualizes',
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $ctxLink = $this->json();
        $this->client->request('PATCH', "/api/v1/projects/$pid/external-statement-links/{$ctxLink['id']}/reject", server: ['CONTENT_TYPE' => 'application/json'], content: json_encode(['reviewed_by' => 'qualified-reviewer', 'relationship' => 'not_comparable', 'comparability_note' => 'Different endpoint class.']));
        self::assertResponseIsSuccessful();
        self::assertSame('not_comparable', $this->json()['relationship']);
        self::assertSame('rejected', $this->json()['review_status']);

        $this->client->request('GET', "/api/v1/projects/$pid/external-statements");
        self::assertResponseIsSuccessful();
        $list = $this->json();
        self::assertSame(2, $list['summary']['total']);
        self::assertSame(1, $list['summary']['links_approved']);
        self::assertSame(1, $list['summary']['links_rejected']);
        self::assertSame(1, $list['summary']['captured_verified']);

        $this->client->request('GET', "/api/v1/projects/$pid/audit-log");
        $actions = array_column($this->json()['entries'], 'action');
        self::assertContains('approve', $actions);
        self::assertContains('reject', $actions);
        self::assertContains('review_gate', $actions);
    }

    public function testCrossProjectLinksAreRejected(): void
    {
        [$projectA] = $this->fixture('A');
        [, , , $claimB] = $this->fixture('B');
        $pid = $projectA->getId()->toRfc4122();
        $this->client->jsonRequest('POST', "/api/v1/projects/$pid/external-statements/import", $this->capturePayload());
        $statement = $this->json()['statements'][0];

        $this->client->jsonRequest('POST', "/api/v1/projects/$pid/external-statement-links", [
            'external_statement_id' => $statement['id'],
            'target_type' => 'claim',
            'target_id' => $claimB->getId()->toRfc4122(),
            'relationship' => 'contextualizes',
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        self::assertCount(0, $this->em->getRepository(ExternalStatementLink::class)->findAll());
    }

    public function testExternalStatementsNeverSatisfyClaimEvidenceAndRestrictedOnesNeverExport(): void
    {
        [$project, $cou, , , $assessment] = $this->fixture('C');
        $pid = $project->getId()->toRfc4122();

        // A decision-informing claim with NO validation evidence.
        $claim = (new ClaimNode())->setClaimId('CLAIM-DI-' . uniqid())->setProject($project)->setContextOfUse($cou)
            ->setClaimText('Decision-informing claim without internal evidence.')->setConfidence('decision_informing')->setReviewStatus('approved');
        $this->em->persist($claim);
        $this->em->flush();

        $this->client->jsonRequest('POST', "/api/v1/projects/$pid/external-statements/import", $this->capturePayload());
        $statements = $this->json()['statements'];
        $publicId = $statements[0]['id'];
        $restrictedId = $statements[1]['id'];

        $this->client->request('PATCH', "/api/v1/projects/$pid/external-statements/$publicId/verify", server: ['CONTENT_TYPE' => 'application/json'], content: json_encode(['reviewed_by' => 'qualified-reviewer']));
        self::assertResponseIsSuccessful();

        // Approved *supporting* external link on the claim...
        $this->client->jsonRequest('POST', "/api/v1/projects/$pid/external-statement-links", [
            'external_statement_id' => $publicId, 'target_type' => 'claim', 'target_id' => $claim->getClaimId(),
            'relationship' => 'supports', 'review_status' => 'approved', 'reviewed_by' => 'qualified-reviewer',
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        // ...and an approved contextual link on a restricted statement, plus a pending suggestion.
        $this->client->jsonRequest('POST', "/api/v1/projects/$pid/external-statement-links", [
            'external_statement_id' => $restrictedId, 'target_type' => 'requirement_assessment', 'target_id' => $assessment->getId()->toRfc4122(),
            'relationship' => 'contextualizes', 'review_status' => 'approved', 'reviewed_by' => 'qualified-reviewer',
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->client->jsonRequest('POST', "/api/v1/projects/$pid/external-statement-links", [
            'external_statement_id' => $publicId, 'target_type' => 'requirement_assessment', 'target_id' => $assessment->getId()->toRfc4122(),
            'relationship' => 'contextualizes',
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        // The SHACL-mirroring rule still fires: external support is not validation evidence.
        $this->client->request('GET', "/api/v1/projects/$pid/semantic-validation");
        self::assertResponseIsSuccessful();
        $validation = $this->json();
        $claimErrors = array_filter($validation['errors'], static fn(array $i) => $i['field'] === 'supporting_evidence' && str_contains($i['entity'], $claim->getClaimId()));
        self::assertCount(1, $claimErrors, 'Decision-informing claim must still be flagged despite an approved external supporting link.');
        $pending = array_filter($validation["warnings"], static fn(array $i) => $i['rule'] === 'ExternalStatementLinkShape');
        self::assertCount(1, $pending, 'A suggested link surfaces as a non-blocking warning.');
        self::assertSame('external-evidence', array_values($pending)[0]['workspace']);

        $this->client->request('GET', "/api/v1/projects/$pid/export-gate");
        self::assertResponseIsSuccessful();
        self::assertTrue($this->json()['blocked']);

        // JSON-LD export: the public statement is present with its approved link; the restricted one is absent entirely.
        $this->client->request('GET', "/api/v1/projects/$pid/exports/jsonld");
        self::assertResponseIsSuccessful();
        $jsonld = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('https://doi.org/10.5555/test.s1', $jsonld);
        self::assertStringContainsString('"nam:relationship":"supports"', str_replace(' ', '', $jsonld));
        self::assertStringNotContainsString('restricted.example.org', $jsonld);
        self::assertStringNotContainsString('RESTRICTED TEXT', $jsonld);
    }

    /** @return array<string,mixed> */
    private function capturePayload(): array
    {
        return [
            'source_system' => 'loom',
            'captured_by' => 'adapter-test',
            'record' => ['record_uri' => 'https://doi.org/10.5555/test.record', 'source_version' => 'v1', 'licence' => 'CC-BY-4.0', 'access_scope' => 'public'],
            'statements' => [
                ['statement_uri' => 'https://doi.org/10.5555/test.s1', 'kind' => 'curated_statement', 'text' => 'Public statement one.', 'dataset_links' => [['uri' => 'https://doi.org/10.5555/test.data', 'label' => 'data']]],
                ['statement_uri' => 'https://restricted.example.org/s2', 'kind' => 'analytical_finding', 'text' => 'RESTRICTED TEXT must not export.', 'access_scope' => 'restricted', 'licence' => 'no redistribution'],
            ],
        ];
    }

    /** @return array{0: Project, 1: ContextOfUseCard, 2: EvidenceItem, 3: ClaimNode, 4: RequirementAssessment} */
    private function fixture(string $suffix): array
    {
        $project = (new Project())->setName("Project $suffix " . uniqid())->setDrugName("Compound $suffix");
        $this->em->persist($project);

        $cou = (new ContextOfUseCard())->setCouId('COU-' . $suffix . '-' . uniqid())->setProject($project)->setNamType('Organoid')
            ->setRegulatoryQuestion('Can the NAM inform the declared use?')->setIntendedUse('supportive evidence')->setDecisionSupported('prioritization')
            ->setBiologicalDomain('hepatotoxicity')->setEndpointClass('cytotoxicity')->setRegulatoryConfidenceLevel('supportive');
        $this->em->persist($cou);

        $study = (new NAMStudy())->setStudyId('STUDY-' . $suffix . '-' . uniqid())->setProject($project)->setContextOfUse($cou)->setTitle("Study $suffix");
        $this->em->persist($study);

        $evidence = (new EvidenceItem())->setEvidenceId('EV-' . $suffix . '-' . uniqid())->setStudy($study)->setDomain('reference_compound_performance')
            ->setQuestion('Does the model classify the reference panel correctly?')->setEvidenceType('concordance')->setStatus('met');
        $this->em->persist($evidence);

        $claim = (new ClaimNode())->setClaimId('CLAIM-' . $suffix . '-' . uniqid())->setProject($project)->setContextOfUse($cou)
            ->setClaimText("Claim $suffix")->setConfidence('supportive')->setSupportingEvidence([$evidence->getEvidenceId()])->setReviewStatus('approved');
        $this->em->persist($claim);

        $profile = (new GuidanceProfile())->setProfileKey('test-profile-' . uniqid())->setAuthority('Example Authority')->setTitle('Example guidance')
            ->setCanonicalSourceUrl('https://example.org/guidance')->setStatus(GuidanceProfile::STATUS_DRAFT)->setVersion('1.0')->setJurisdiction('Example');
        $this->em->persist($profile);
        $requirement = (new GuidanceRequirement())->setProfile($profile)->setRequirementKey('REQ-TECH-003')->setTitle('Characterize predictive performance')
            ->setRequirementText('Describe predictive performance for the CoU.')->setEvidenceDomain('technical_characterization')->setSourceLocator('section 2.1');
        $this->em->persist($requirement);
        $assessment = (new RequirementAssessment())->setProject($project)->setContextOfUse($cou)->setRequirement($requirement)
            ->setStatus(RequirementAssessment::STATUS_PARTIAL)->setAssessmentOrigin(RequirementAssessment::ORIGIN_HUMAN);
        $this->em->persist($assessment);

        $this->em->flush();
        return [$project, $cou, $evidence, $claim, $assessment];
    }

    /** @return array<string,mixed> */
    private function json(): array
    {
        return json_decode((string) $this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
    }
}
