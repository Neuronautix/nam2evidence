<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Entity\NamCore\GuidanceProfile;
use App\Entity\NamCore\GuidanceRequirement;
use App\Entity\NamCore\RequirementAssessment;
use App\Entity\NamCore\RequirementEvidenceLink;
use App\Entity\ContextOfUseCard;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class GuidanceProfileSeedCommandTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private Application $application;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->em->getConnection()->executeStatement('TRUNCATE TABLE namcore_guidance_profile, projects RESTART IDENTITY CASCADE');
        $this->application = new Application(static::$kernel);
    }

    public function testFdaProfileIsDraftSourceCitedConditionalAndIdempotent(): void
    {
        $first = $this->runCommand('app:load-guidance-profiles');
        self::assertSame(Command::SUCCESS, $first->getStatusCode());

        /** @var GuidanceProfile|null $profile */
        $profile = $this->em->getRepository(GuidanceProfile::class)->findOneBy([
            'profileKey' => 'fda-nam-general-considerations-2026',
            'version' => '1.0.0',
        ]);
        self::assertNotNull($profile);
        self::assertSame(GuidanceProfile::STATUS_DRAFT, $profile->getStatus());
        self::assertSame('2026-03-18', $profile->getPublicationDate()?->format('Y-m-d'));
        self::assertSame('https://www.fda.gov/media/191589/download', $profile->getCanonicalSourceUrl());
        self::assertSame('FDA-2025-D-6131', $profile->getSourceMetadata()['docket_number']);
        self::assertNotEmpty($profile->getSourceMetadata()['seed_sha256']);

        /** @var GuidanceRequirement[] $requirements */
        $requirements = $this->em->getRepository(GuidanceRequirement::class)->findBy(['profile' => $profile]);
        self::assertCount(16, $requirements);
        foreach ($requirements as $requirement) {
            self::assertNotSame('', trim($requirement->getSourceLocator()));
            self::assertNotSame(GuidanceRequirement::IMPORTANCE_REQUIRED, $requirement->getImportance());
        }

        /** @var GuidanceRequirement|null $comparator */
        $comparator = $this->em->getRepository(GuidanceRequirement::class)->findOneBy([
            'profile' => $profile,
            'requirementKey' => 'FDA-NAM-FIT-001',
        ]);
        self::assertNotNull($comparator);
        self::assertSame('conditional', $comparator->getApplicabilityRules()['mode']);
        self::assertSame('not_applicable', $comparator->getApplicabilityRules()['otherwise']);
        self::assertTrue($comparator->getApplicabilityRules()['requires_human_interpretation']);

        $second = $this->runCommand('app:load-guidance-profiles');
        self::assertSame(Command::SUCCESS, $second->getStatusCode());
        self::assertCount(1, $this->em->getRepository(GuidanceProfile::class)->findAll());
        self::assertCount(16, $this->em->getRepository(GuidanceRequirement::class)->findBy(['profile' => $profile]));
    }

    public function testSameVersionChangedSeedFingerprintIsRejected(): void
    {
        self::assertSame(Command::SUCCESS, $this->runCommand('app:load-guidance-profiles')->getStatusCode());

        /** @var GuidanceProfile $profile */
        $profile = $this->em->getRepository(GuidanceProfile::class)->findOneBy([
            'profileKey' => 'fda-nam-general-considerations-2026',
            'version' => '1.0.0',
        ]);
        $metadata = $profile->getSourceMetadata();
        $metadata['seed_sha256'] = str_repeat('0', 64);
        $profile->setSourceMetadata($metadata);
        $this->em->flush();

        $attempt = $this->runCommand('app:load-guidance-profiles');
        self::assertSame(Command::FAILURE, $attempt->getStatusCode());
        self::assertStringContainsString('seed content changed', $attempt->getDisplay());
        self::assertStringContainsString('profile version', $attempt->getDisplay());
    }

    public function testDemoAssessmentsProduceMixedEvidenceCoverageStates(): void
    {
        $demo = $this->runCommand('app:load-demo-data', ['--force' => true]);
        self::assertSame(Command::SUCCESS, $demo->getStatusCode());

        $guidance = $this->runCommand('app:load-guidance-profiles', ['--with-demo-assessments' => true]);
        self::assertSame(Command::SUCCESS, $guidance->getStatusCode());

        /** @var ContextOfUseCard $cou */
        $cou = $this->em->getRepository(ContextOfUseCard::class)->findOneBy(['couId' => 'COU-HEP-001']);
        /** @var GuidanceProfile $profile */
        $profile = $this->em->getRepository(GuidanceProfile::class)->findOneBy([
            'profileKey' => 'fda-nam-general-considerations-2026',
            'version' => '1.0.0',
        ]);

        /** @var RequirementAssessment[] $assessments */
        $assessments = $this->em->getRepository(RequirementAssessment::class)->findBy([
            'project' => $cou->getProject(),
            'contextOfUse' => $cou,
        ]);
        self::assertCount(16, $assessments);

        $statuses = array_values(array_unique(array_map(static fn(RequirementAssessment $a) => $a->getStatus(), $assessments)));
        self::assertContains(RequirementAssessment::STATUS_SUPPORTED, $statuses);
        self::assertContains(RequirementAssessment::STATUS_PARTIAL, $statuses);
        self::assertContains(RequirementAssessment::STATUS_MISSING, $statuses);
        self::assertContains(RequirementAssessment::STATUS_REQUIRES_HUMAN, $statuses);
        self::assertContains(RequirementAssessment::STATUS_NOT_APPLICABLE, $statuses);

        foreach ($assessments as $assessment) {
            self::assertSame(RequirementAssessment::REVIEW_HUMAN_REQUIRED, $assessment->getReviewStatus());
            self::assertSame(RequirementAssessment::ORIGIN_HUMAN, $assessment->getAssessmentOrigin());
        }

        self::assertGreaterThan(
            0,
            $this->em->getRepository(RequirementEvidenceLink::class)->count([]),
            'Demo profile should exercise explicit evidence links as well as status-only assessments.',
        );

        // Re-running the demo seeder preserves the existing assessment set.
        self::assertSame(
            Command::SUCCESS,
            $this->runCommand('app:load-guidance-profiles', ['--with-demo-assessments' => true])->getStatusCode(),
        );
        self::assertCount(16, $this->em->getRepository(RequirementAssessment::class)->findBy([
            'project' => $cou->getProject(),
            'contextOfUse' => $cou,
        ]));
    }

    /** @param array<string,mixed> $arguments */
    private function runCommand(string $name, array $arguments = []): CommandTester
    {
        $tester = new CommandTester($this->application->find($name));
        $tester->execute($arguments, ['interactive' => false]);
        return $tester;
    }
}
