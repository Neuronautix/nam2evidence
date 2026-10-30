<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\ClaimNode;
use App\Entity\ContextOfUseCard;
use App\Entity\EvidenceItem;
use App\Entity\NamCore\ExternalStatement;
use App\Entity\NamCore\ExternalStatementLink;
use App\Entity\NamCore\RequirementAssessment;
use App\Entity\Project;
use App\Service\NamCore\AuditLogger;
use App\Service\NamCore\ExternalStatementImporter;
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
 * Loads the synthetic Knowledge-Loom-shaped statements in demo/loom_statements.json
 * into the COU-HEP-001 demo project and creates *suggested* links.
 *
 * Without --reviewed the project is left in the "before" state: statements
 * captured, links suggested, nothing decided. With --reviewed the fixture's
 * recorded reviewer decisions are applied (verify, approve, reject) so the
 * "after" state can be shown. Both runs are idempotent.
 */
#[AsCommand(
    name: 'app:load-loom-demo',
    description: 'Capture synthetic Knowledge Loom statements into the COU-HEP-001 demo and suggest links for human review.',
)]
final class LoadLoomDemoCommand extends Command
{
    private const COU_ID = 'COU-HEP-001';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ExternalStatementImporter $importer,
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
            ->addOption('file', null, InputOption::VALUE_REQUIRED, 'Fixture path (default demo/loom_statements.json).')
            ->addOption('reviewed', null, InputOption::VALUE_NONE, 'Apply the fixture\'s recorded reviewer decisions (the "after" state).');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Loading synthetic Knowledge Loom statements into COU-HEP-001');

        $file = trim((string) ($input->getOption('file') ?? ''));
        $path = $file !== '' ? (str_starts_with($file, '/') ? $file : dirname($this->projectDir) . '/' . ltrim($file, '/')) : dirname($this->projectDir) . '/demo/loom_statements.json';
        if (!is_file($path)) {
            $io->error(sprintf('Fixture not found: %s', $path));
            return Command::FAILURE;
        }

        /** @var ContextOfUseCard|null $cou */
        $cou = $this->em->getRepository(ContextOfUseCard::class)->findOneBy(['couId' => self::COU_ID]);
        if ($cou === null) {
            $io->error(sprintf('Demo CoU %s not found. Run app:load-demo-data --force first (and app:load-guidance-profiles --with-demo-assessments for requirement targets).', self::COU_ID));
            return Command::FAILURE;
        }
        $project = $cou->getProject();

        try {
            $fixture = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            $io->error('Fixture is not valid JSON: ' . $e->getMessage());
            return Command::FAILURE;
        }
        if (!is_array($fixture)) {
            $io->error('Fixture must be a JSON object.');
            return Command::FAILURE;
        }

        $result = $this->importer->import($project, $fixture, flush: true);
        if ($result['errors'] !== [] || $result['conflicts'] !== []) {
            foreach ($result['errors'] as $error) $io->error($error);
            foreach ($result['conflicts'] as $conflict) $io->error($conflict['message'] . ' (' . $conflict['statement_uri'] . ')');
            return Command::FAILURE;
        }
        foreach ($result['created'] as $statement) {
            $this->audit->log($project, 'ExternalStatement', $statement->getId()->toRfc4122(), 'import', null, [
                'statement_uri' => $statement->getStatementUri(), 'source_version' => $statement->getSourceVersion(),
                'payload_sha256' => $statement->getPayloadSha256(), 'access_scope' => $statement->getAccessScope(), 'fixture' => true,
            ], 'Synthetic Knowledge Loom demonstration statement; not a real finding.', 'demo-seed', flush: false);
        }
        $this->em->flush();
        $io->writeln(sprintf('Statements: %d captured, %d already present.', count($result['created']), count($result['replayed'])));

        $statementsByUri = [];
        foreach ($this->em->getRepository(ExternalStatement::class)->findBy(['project' => $project]) as $s) {
            $statementsByUri[$s->getStatementUri()] = $s;
        }

        $linksCreated = 0; $linksSkipped = 0; $linksUnresolved = [];
        foreach ((array) ($fixture['suggested_links'] ?? []) as $row) {
            if (!is_array($row)) continue;
            $statement = $statementsByUri[(string) ($row['statement_uri'] ?? '')] ?? null;
            if ($statement === null) { $linksUnresolved[] = 'unknown statement ' . ($row['statement_uri'] ?? '?'); continue; }

            $link = (new ExternalStatementLink())
                ->setExternalStatement($statement)
                ->setRelationship((string) ($row['relationship'] ?? ExternalStatementLink::REL_CONTEXTUALIZES))
                ->setLinkOrigin(ExternalStatementLink::ORIGIN_DETERMINISTIC)
                ->setReviewStatus(ExternalStatementLink::REVIEW_SUGGESTED)
                ->setProvenance(['fixture' => true, 'source' => 'demo/loom_statements.json', 'rule' => (string) ($row['rule'] ?? '')]);
            if (!$this->attachTarget($project, $link, (string) ($row['target_type'] ?? ''), (string) ($row['target'] ?? ''))) {
                $linksUnresolved[] = sprintf('%s %s (not loaded in this database)', $row['target_type'] ?? '?', $row['target'] ?? '?');
                continue;
            }
            if ($this->findExistingLink($link) !== null) { $linksSkipped++; continue; }

            $violations = $this->validator->validate($link);
            if (count($violations) > 0) {
                $io->error(sprintf('Suggested link %s -> %s invalid: %s', $statement->getStatementUri(), $row['target'] ?? '?', (string) $violations));
                return Command::FAILURE;
            }
            $this->em->persist($link);
            $this->em->flush();
            $this->audit->log($project, 'ExternalStatementLink', $link->getId()->toRfc4122(), 'create', null, [
                'statement_uri' => $statement->getStatementUri(), 'target_type' => $link->getTargetType(), 'target' => (string) ($row['target'] ?? ''),
                'relationship' => $link->getRelationship(), 'review_status' => $link->getReviewStatus(), 'fixture' => true,
            ], 'Deterministic suggestion from the demo fixture; requires a human decision.', 'demo-seed');
            $linksCreated++;
        }
        $io->writeln(sprintf('Suggested links: %d created, %d already present.', $linksCreated, $linksSkipped));
        foreach ($linksUnresolved as $u) $io->warning('Skipped suggestion: ' . $u);

        if ((bool) $input->getOption('reviewed')) {
            $this->applyDecisions($io, $project, $fixture, $statementsByUri);
        } else {
            $io->note('Left in the "before" state: every link is a suggestion awaiting a reviewer. Re-run with --reviewed for the recorded demo decisions.');
        }

        $io->success('Knowledge Loom demo load completed.');
        return Command::SUCCESS;
    }

    /** @param array<string,ExternalStatement> $statementsByUri */
    private function applyDecisions(SymfonyStyle $io, Project $project, array $fixture, array $statementsByUri): void
    {
        $decisions = is_array($fixture['reviewed_decisions'] ?? null) ? $fixture['reviewed_decisions'] : [];
        $reviewer = trim((string) ($decisions['reviewer'] ?? 'demo-reviewer'));

        $verified = 0;
        foreach ((array) ($decisions['verify_captures'] ?? []) as $uri) {
            $s = $statementsByUri[(string) $uri] ?? null;
            if ($s === null || $s->getCaptureStatus() === ExternalStatement::CAPTURE_VERIFIED) continue;
            $before = ['capture_status' => $s->getCaptureStatus()];
            $s->setCaptureStatus(ExternalStatement::CAPTURE_VERIFIED)->setVerifiedBy($reviewer)->setVerifiedAt(new \DateTimeImmutable());
            $this->em->flush();
            $this->audit->log($project, 'ExternalStatement', $s->getId()->toRfc4122(), 'review_gate', $before, ['capture_status' => $s->getCaptureStatus()],
                'Demo reviewer confirmed the capture matches its (synthetic) source.', $reviewer);
            $verified++;
        }

        $decided = 0; $skipped = 0;
        foreach ((array) ($decisions['decisions'] ?? []) as $row) {
            if (!is_array($row)) continue;
            $s = $statementsByUri[(string) ($row['statement_uri'] ?? '')] ?? null;
            if ($s === null) continue;
            $link = $this->findLinkByTargetKey($s, (string) ($row['target'] ?? ''));
            if ($link === null) { $skipped++; continue; }
            if ($link->getReviewStatus() !== ExternalStatementLink::REVIEW_SUGGESTED) { $skipped++; continue; }

            $decision = (string) ($row['decision'] ?? '');
            $before = ['review_status' => $link->getReviewStatus(), 'relationship' => $link->getRelationship()];
            if (isset($row['relationship'])) $link->setRelationship((string) $row['relationship']);
            $link->setReviewStatus($decision === 'approved' ? ExternalStatementLink::REVIEW_APPROVED : ExternalStatementLink::REVIEW_REJECTED)
                ->setReviewedBy($reviewer)->setReviewedAt(new \DateTimeImmutable())
                ->setReviewerNote(isset($row['note']) ? (string) $row['note'] : null)
                ->setComparabilityNote(isset($row['comparability_note']) ? (string) $row['comparability_note'] : $link->getComparabilityNote());
            $violations = $this->validator->validate($link);
            if (count($violations) > 0) {
                throw new \RuntimeException(sprintf('Demo decision for %s -> %s is invalid: %s', $s->getStatementUri(), $row['target'] ?? '?', (string) $violations));
            }
            $this->em->flush();
            $this->audit->log($project, 'ExternalStatementLink', $link->getId()->toRfc4122(), $link->isApproved() ? 'approve' : 'reject', $before,
                ['review_status' => $link->getReviewStatus(), 'relationship' => $link->getRelationship()], $link->getReviewerNote(), $reviewer);
            $decided++;
        }
        $io->writeln(sprintf('Reviewer decisions: %d captures verified, %d links decided, %d already decided or unavailable.', $verified, $decided, $skipped));
    }

    private function attachTarget(Project $project, ExternalStatementLink $link, string $type, string $key): bool
    {
        switch ($type) {
            case ExternalStatementLink::TARGET_REQUIREMENT_ASSESSMENT:
                foreach ($this->em->getRepository(RequirementAssessment::class)->findBy(['project' => $project]) as $a) {
                    if ($a->getRequirement()->getRequirementKey() === $key) { $link->setRequirementAssessment($a); return true; }
                }
                return false;
            case ExternalStatementLink::TARGET_CLAIM:
                $claim = $this->em->getRepository(ClaimNode::class)->findOneBy(['project' => $project, 'claimId' => $key]);
                if ($claim === null) return false;
                $link->setClaim($claim);
                return true;
            case ExternalStatementLink::TARGET_EVIDENCE_ITEM:
                $item = $this->em->getRepository(EvidenceItem::class)->findOneBy(['evidenceId' => $key]);
                if ($item === null || $item->getStudy()->getProject()->getId()->toRfc4122() !== $project->getId()->toRfc4122()) return false;
                $link->setEvidenceItem($item);
                return true;
        }
        return false;
    }

    private function findExistingLink(ExternalStatementLink $candidate): ?ExternalStatementLink
    {
        $criteria = ['externalStatement' => $candidate->getExternalStatement(), 'relationship' => $candidate->getRelationship()];
        $criteria[match ($candidate->getTargetType()) {
            ExternalStatementLink::TARGET_REQUIREMENT_ASSESSMENT => 'requirementAssessment',
            ExternalStatementLink::TARGET_CLAIM => 'claim',
            default => 'evidenceItem',
        }] = match ($candidate->getTargetType()) {
            ExternalStatementLink::TARGET_REQUIREMENT_ASSESSMENT => $candidate->getRequirementAssessment(),
            ExternalStatementLink::TARGET_CLAIM => $candidate->getClaim(),
            default => $candidate->getEvidenceItem(),
        };
        $found = $this->em->getRepository(ExternalStatementLink::class)->findOneBy($criteria);
        return $found instanceof ExternalStatementLink ? $found : null;
    }

    private function findLinkByTargetKey(ExternalStatement $statement, string $key): ?ExternalStatementLink
    {
        foreach ($this->em->getRepository(ExternalStatementLink::class)->findBy(['externalStatement' => $statement]) as $link) {
            $targetKey = match ($link->getTargetType()) {
                ExternalStatementLink::TARGET_REQUIREMENT_ASSESSMENT => $link->getRequirementAssessment()?->getRequirement()->getRequirementKey(),
                ExternalStatementLink::TARGET_CLAIM => $link->getClaim()?->getClaimId(),
                default => $link->getEvidenceItem()?->getEvidenceId(),
            };
            if ($targetKey === $key) return $link;
        }
        return null;
    }
}
