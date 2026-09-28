<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\ContextOfUseCard;
use App\Entity\EvidenceItem;
use App\Entity\NamCore\GuidanceProfile;
use App\Entity\NamCore\GuidanceRequirement;
use App\Entity\NamCore\RequirementAssessment;
use App\Entity\NamCore\RequirementEvidenceLink;
use App\Service\NamCore\AuditLogger;
use App\Service\NamCore\GuidanceProfileSeedLoader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Load versioned regulatory/reference guidance profiles from standards/reference.
 *
 * Seed content is immutable by profile_key + version: modifying a loaded seed
 * without bumping its version fails instead of silently changing the evidence
 * framework used by existing project assessments.
 */
#[AsCommand(
    name: 'app:load-guidance-profiles',
    description: 'Load versioned, source-cited regulatory/reference guidance profiles.',
)]
final class LoadGuidanceProfilesCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly GuidanceProfileSeedLoader $loader,
        private readonly ValidatorInterface $validator,
        private readonly AuditLogger $audit,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('file', null, InputOption::VALUE_REQUIRED, 'Load one seed JSON file instead of every file in standards/reference.')
            ->addOption('with-demo-assessments', null, InputOption::VALUE_NONE, 'Seed synthetic COU-HEP-001 assessments after loading the profile.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Loading versioned guidance profiles');

        $paths = $this->resolveSeedPaths($input->getOption('file'));
        if ($paths === []) {
            $io->error('No guidance-profile seed files found.');
            return Command::FAILURE;
        }

        try {
            $rows = [];
            foreach ($paths as $path) {
                $result = $this->loader->loadFile($path);
                $profile = $result['profile'];
                $rows[] = [
                    $profile->getProfileKey(),
                    $profile->getVersion(),
                    $profile->getStatus(),
                    $result['created'] ? 'created' : 'unchanged',
                    (string) $result['requirements_total'],
                ];
            }

            $io->table(['Profile', 'Version', 'Status', 'Load result', 'Requirements'], $rows);

            if ((bool) $input->getOption('with-demo-assessments')) {
                $this->seedDemoAssessments($io);
            }
        } catch (\Throwable $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }

        $io->success('Guidance profile load completed.');
        return Command::SUCCESS;
    }

    /** @return list<string> */
    private function resolveSeedPaths(mixed $fileOption): array
    {
        $base = dirname($this->projectDir);
        $file = trim((string) ($fileOption ?? ''));
        if ($file !== '') {
            $path = str_starts_with($file, '/') ? $file : $base . '/' . ltrim($file, '/');
            return [$path];
        }

        $paths = glob($base . '/standards/reference/*.json') ?: [];
        sort($paths, SORT_NATURAL | SORT_FLAG_CASE);
        return array_values($paths);
    }

    private function seedDemoAssessments(SymfonyStyle $io): void
    {
        $path = dirname($this->projectDir) . '/demo/fda_guidance_assessments.json';
        if (!is_file($path) || !is_readable($path)) {
            throw new \RuntimeException(sprintf('Demo assessment fixture not found: %s', $path));
        }

        $data = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($data) || !is_array($data['assessments'] ?? null)) {
            throw new \RuntimeException('Demo assessment fixture must contain an assessments array.');
        }

        $profileKey = trim((string) ($data['profile_key'] ?? ''));
        $profileVersion = trim((string) ($data['profile_version'] ?? ''));
        $couId = trim((string) ($data['cou_id'] ?? ''));

        /** @var GuidanceProfile|null $profile */
        $profile = $this->em->getRepository(GuidanceProfile::class)->findOneBy([
            'profileKey' => $profileKey,
            'version' => $profileVersion,
        ]);
        if ($profile === null) {
            throw new \RuntimeException(sprintf('Demo profile %s version %s is not loaded.', $profileKey, $profileVersion));
        }

        /** @var ContextOfUseCard|null $cou */
        $cou = $this->em->getRepository(ContextOfUseCard::class)->findOneBy(['couId' => $couId]);
        if ($cou === null) {
            throw new \RuntimeException(sprintf('Demo CoU %s not found. Run app:load-demo-data --force first.', $couId));
        }
        $project = $cou->getProject();

        $created = 0;
        $skipped = 0;
        foreach ($data['assessments'] as $entry) {
            if (!is_array($entry)) continue;

            $requirementKey = trim((string) ($entry['requirement_key'] ?? ''));
            /** @var GuidanceRequirement|null $requirement */
            $requirement = $this->em->getRepository(GuidanceRequirement::class)->findOneBy([
                'profile' => $profile,
                'requirementKey' => $requirementKey,
            ]);
            if ($requirement === null) {
                throw new \RuntimeException(sprintf('Demo assessment references unknown requirement %s.', $requirementKey));
            }

            $existing = $this->em->getRepository(RequirementAssessment::class)->findOneBy([
                'project' => $project,
                'contextOfUse' => $cou,
                'requirement' => $requirement,
            ]);
            if ($existing instanceof RequirementAssessment) {
                $skipped++;
                continue;
            }

            $assessment = (new RequirementAssessment())
                ->setProject($project)
                ->setContextOfUse($cou)
                ->setRequirement($requirement)
                ->setStatus(trim((string) ($entry['status'] ?? RequirementAssessment::STATUS_REQUIRES_HUMAN)))
                ->setRationale(isset($entry['rationale']) ? (string) $entry['rationale'] : null)
                ->setAssessmentOrigin(RequirementAssessment::ORIGIN_HUMAN)
                ->setReviewStatus(RequirementAssessment::REVIEW_HUMAN_REQUIRED);

            $this->assertValid($assessment, 'Demo RequirementAssessment ' . $requirementKey);
            $this->em->persist($assessment);

            foreach (($entry['evidence_links'] ?? []) as $linkData) {
                if (!is_array($linkData)) continue;
                $evidenceId = trim((string) ($linkData['evidence_id'] ?? ''));
                /** @var EvidenceItem|null $evidence */
                $evidence = $this->em->getRepository(EvidenceItem::class)->findOneBy(['evidenceId' => $evidenceId]);
                if ($evidence === null) {
                    throw new \RuntimeException(sprintf('Demo assessment %s references missing evidence item %s.', $requirementKey, $evidenceId));
                }

                $link = (new RequirementEvidenceLink())
                    ->setAssessment($assessment)
                    ->setEvidenceItem($evidence)
                    ->setRelationship(trim((string) ($linkData['relationship'] ?? RequirementEvidenceLink::REL_CONTEXTUALIZES)))
                    ->setProvenance([
                        'fixture' => true,
                        'source' => 'demo/fda_guidance_assessments.json',
                        'profile_key' => $profileKey,
                        'profile_version' => $profileVersion,
                    ]);
                $this->assertValid($link, 'Demo RequirementEvidenceLink ' . $requirementKey . ' -> ' . $evidenceId);
                $this->em->persist($link);
            }

            $this->em->flush();
            $this->audit->log(
                $project,
                'RequirementAssessment',
                $assessment->getId()->toRfc4122(),
                'create',
                null,
                [
                    'requirement_key' => $requirementKey,
                    'status' => $assessment->getStatus(),
                    'review_status' => $assessment->getReviewStatus(),
                    'fixture' => true,
                ],
                'Synthetic FDA guidance-profile demonstration fixture; not a regulatory assessment.',
                'demo-seed',
            );
            $created++;
        }

        $io->note(sprintf(
            'FDA guidance demo assessments: %d created, %d skipped. All remain human_review_required and are synthetic demonstration data.',
            $created,
            $skipped,
        ));
    }

    private function assertValid(object $entity, string $label): void
    {
        $violations = $this->validator->validate($entity);
        if (count($violations) === 0) return;

        $messages = [];
        foreach ($violations as $violation) {
            $messages[] = sprintf('%s: %s', $violation->getPropertyPath(), $violation->getMessage());
        }
        throw new \RuntimeException(sprintf('%s validation failed: %s', $label, implode('; ', $messages)));
    }
}
