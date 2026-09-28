<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller;

use App\Entity\ContextOfUseCard;
use App\Entity\EvidenceItem;
use App\Entity\NamCore\GuidanceProfile;
use App\Entity\NamCore\GuidanceRequirement;
use App\Entity\NamCore\RequirementAssessment;
use App\Entity\NamCore\RequirementEvidenceLink;
use App\Entity\NAMStudy;
use App\Entity\Project;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class RegulatoryEvidenceApiTest extends WebTestCase
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

    public function testGuidanceProfilesExposeDistinctVersionsAndSourceProvenance(): void
    {
        [$profileV1, $requirement] = $this->createProfileAndRequirement('1.0');
        $profileV2 = $this->createProfile('1.1');
        $this->em->flush();

        $this->client->request('GET', '/api/v1/guidance-profiles?authority=Example');
        self::assertResponseIsSuccessful();
        $list = $this->json();
        self::assertSame(2, $list['count']);
        self::assertSame(['1.0', '1.1'], array_column($list['profiles'], 'version'));

        $profileId = $profileV1->getId()->toRfc4122();
        $this->client->request('GET', "/api/v1/guidance-profiles/$profileId/requirements");
        self::assertResponseIsSuccessful();
        $payload = $this->json();
        self::assertSame('https://example.org/guidance', $payload['profile']['canonical_source_url']);
        self::assertSame('section 2.1', $payload['requirements'][0]['source_locator']);
        self::assertSame($requirement->getRequirementKey(), $payload['requirements'][0]['requirement_key']);
        self::assertSame('1.0', $payload['requirements'][0]['profile_version']);

        self::assertNotSame($profileV1->getId()->toRfc4122(), $profileV2->getId()->toRfc4122());
    }

    public function testAssessmentCreationHumanReviewAndAuditAreExplicit(): void
    {
        [, $requirement] = $this->createProfileAndRequirement('1.0');
        [$project, $cou, $evidence] = $this->createProjectFixture('A');
        $this->em->flush();

        $projectId = $project->getId()->toRfc4122();
        $this->client->jsonRequest('POST', "/api/v1/projects/$projectId/requirement-assessments", [
            'context_of_use_id' => $cou->getId()->toRfc4122(),
            'requirement_id' => $requirement->getId()->toRfc4122(),
            'status' => RequirementAssessment::STATUS_SUPPORTED,
            'assessment_origin' => RequirementAssessment::ORIGIN_DETERMINISTIC,
            'review_status' => RequirementAssessment::REVIEW_HUMAN_REQUIRED,
            'rationale' => 'Structured evidence satisfies the deterministic coverage rule.',
            'evidence_links' => [[
                'evidence_item_id' => $evidence->getId()->toRfc4122(),
                'relationship' => RequirementEvidenceLink::REL_SUPPORTS,
                'provenance' => ['selector' => 'evidence_domain'],
            ]],
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $created = $this->json();
        self::assertSame('supported', $created['status']);
        self::assertSame('deterministic_rule', $created['assessment_origin']);
        self::assertSame('human_review_required', $created['review_status']);
        self::assertCount(1, $created['evidence_links']);

        $assessmentId = $created['id'];
        $this->client->request(
            'PATCH',
            "/api/v1/projects/$projectId/requirement-assessments/$assessmentId",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'review_status' => RequirementAssessment::REVIEW_HUMAN_REVIEWED,
                'reviewed_by' => 'qualified-reviewer',
                'reviewer_comment' => 'Evidence linkage checked.',
                'reason' => 'Manual scientific review',
            ], JSON_THROW_ON_ERROR),
        );
        self::assertResponseIsSuccessful();
        $reviewed = $this->json();
        self::assertSame('human_reviewed', $reviewed['review_status']);
        self::assertSame('qualified-reviewer', $reviewed['reviewed_by']);
        self::assertNotNull($reviewed['reviewed_at']);

        $profileId = $requirement->getProfile()->getId()->toRfc4122();
        $this->client->request('GET', "/api/v1/projects/$projectId/requirement-assessments?profile_id=$profileId");
        self::assertResponseIsSuccessful();
        self::assertSame(1, $this->json()['count']);

        $this->client->request('GET', "/api/v1/projects/$projectId/audit-log");
        self::assertResponseIsSuccessful();
        $audit = $this->json();
        $actions = array_column($audit['entries'], 'action');
        self::assertContains('create', $actions);
        self::assertContains('update', $actions);
    }

    public function testAssessmentRejectsCrossProjectContextAndEvidence(): void
    {
        [, $requirement] = $this->createProfileAndRequirement('1.0');
        [$projectA, $couA] = $this->createProjectFixture('A');
        [, $couB, $evidenceB] = $this->createProjectFixture('B');
        $this->em->flush();

        $projectAId = $projectA->getId()->toRfc4122();

        $this->client->jsonRequest('POST', "/api/v1/projects/$projectAId/requirement-assessments", [
            'context_of_use_id' => $couB->getId()->toRfc4122(),
            'requirement_id' => $requirement->getId()->toRfc4122(),
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->client->jsonRequest('POST', "/api/v1/projects/$projectAId/requirement-assessments", [
            'context_of_use_id' => $couA->getId()->toRfc4122(),
            'requirement_id' => $requirement->getId()->toRfc4122(),
            'evidence_links' => [[
                'evidence_item_id' => $evidenceB->getId()->toRfc4122(),
                'relationship' => RequirementEvidenceLink::REL_SUPPORTS,
            ]],
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $violations = $this->json()['violations'];
        self::assertSame('evidenceItem', $violations[0]['field']);
    }

    /** @return array{GuidanceProfile,GuidanceRequirement} */
    private function createProfileAndRequirement(string $version): array
    {
        $profile = $this->createProfile($version);
        $requirement = (new GuidanceRequirement())
            ->setProfile($profile)
            ->setRequirementKey('TECH-REPRO-001')
            ->setTitle('Characterize technical reproducibility')
            ->setRequirementText('Technical reproducibility should be characterized for the declared context.')
            ->setEvidenceDomain('technical_reproducibility')
            ->setImportance(GuidanceRequirement::IMPORTANCE_EXPECTED)
            ->setSourceLocator('section 2.1')
            ->setInterpretationNote('Test fixture; not a regulatory conclusion.');
        $this->em->persist($requirement);

        return [$profile, $requirement];
    }

    private function createProfile(string $version): GuidanceProfile
    {
        $profile = (new GuidanceProfile())
            ->setProfileKey('example-nam-guidance')
            ->setAuthority('Example Authority')
            ->setTitle('Example NAM Guidance')
            ->setCanonicalSourceUrl('https://example.org/guidance')
            ->setStatus(GuidanceProfile::STATUS_DRAFT)
            ->setVersion($version)
            ->setSourceVersion('2026-draft')
            ->setJurisdiction('Example')
            ->setSourceHash(hash('sha256', 'example-source-' . $version))
            ->setRetrievedAt(new \DateTimeImmutable('2026-09-28T00:00:00+00:00'))
            ->setSourceMetadata(['fixture' => true]);
        $this->em->persist($profile);

        return $profile;
    }

    /** @return array{Project,ContextOfUseCard,EvidenceItem} */
    private function createProjectFixture(string $suffix): array
    {
        $project = (new Project())->setName("Project $suffix")->setDrugName("Compound $suffix");
        $this->em->persist($project);

        $cou = (new ContextOfUseCard())
            ->setCouId('COU-' . $suffix . '-' . uniqid())
            ->setProject($project)
            ->setNamType('Organoid')
            ->setRegulatoryQuestion('Can the NAM inform the declared use?')
            ->setIntendedUse('supportive evidence')
            ->setDecisionSupported('prioritization')
            ->setBiologicalDomain('hepatotoxicity')
            ->setEndpointClass('cytotoxicity')
            ->setRegulatoryConfidenceLevel('supportive');
        $this->em->persist($cou);

        $study = (new NAMStudy())
            ->setStudyId('STUDY-' . $suffix . '-' . uniqid())
            ->setProject($project)
            ->setContextOfUse($cou)
            ->setTitle("Study $suffix");
        $this->em->persist($study);

        $evidence = (new EvidenceItem())
            ->setEvidenceId('EV-' . $suffix . '-' . uniqid())
            ->setStudy($study)
            ->setDomain('technical_reproducibility')
            ->setQuestion('Is technical reproducibility characterized?')
            ->setEvidenceType('validation_study')
            ->setStatus('met');
        $this->em->persist($evidence);

        return [$project, $cou, $evidence];
    }

    /** @return array<string,mixed> */
    private function json(): array
    {
        return json_decode((string) $this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
    }
}
