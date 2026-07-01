<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\NamCore\OntologyTerm;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Loads the local NAM seed vocabulary (standards/vocab/nam-seed-vocabulary.json)
 * into namcore_ontology_term. Idempotent: terms are upserted by curie, so an
 * existing term with the same curie is skipped rather than duplicated.
 *
 * Usage:
 *   bin/console app:load-ontology-seed
 */
#[AsCommand(
    name: 'app:load-ontology-seed',
    description: 'Load the local NAM seed vocabulary into namcore_ontology_term.',
)]
final class LoadOntologySeedCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Loading NAM seed vocabulary');

        $path = dirname($this->projectDir) . '/standards/vocab/nam-seed-vocabulary.json';

        if (!is_file($path) || !is_readable($path)) {
            $io->error(sprintf('Seed vocabulary file not found or unreadable: %s', $path));
            return Command::FAILURE;
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            $io->error(sprintf('Unable to read seed vocabulary file: %s', $path));
            return Command::FAILURE;
        }

        try {
            $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            $io->error(sprintf('Invalid JSON in %s: %s', $path, $e->getMessage()));
            return Command::FAILURE;
        }

        $terms = (is_array($data) && is_array($data['terms'] ?? null)) ? $data['terms'] : null;
        if ($terms === null) {
            $io->error('Seed vocabulary is missing a "terms" array.');
            return Command::FAILURE;
        }

        $repo = $this->em->getRepository(OntologyTerm::class);
        $created = 0;
        $skipped = 0;

        foreach ($terms as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $curie = trim((string) ($entry['curie'] ?? ''));
            if ($curie === '') {
                continue;
            }

            if ($repo->findOneBy(['curie' => $curie]) !== null) {
                $skipped++;
                continue;
            }

            $synonyms = [];
            if (is_array($entry['synonyms'] ?? null)) {
                $synonyms = array_values(array_map(static fn($s) => (string) $s, $entry['synonyms']));
            }

            $term = (new OntologyTerm())
                ->setLabel((string) ($entry['label'] ?? ''))
                ->setOntologyPrefix((string) ($entry['ontology_prefix'] ?? ''))
                ->setCurie($curie)
                ->setIri(isset($entry['iri']) ? (string) $entry['iri'] : null)
                ->setDefinition(isset($entry['definition']) ? (string) $entry['definition'] : null)
                ->setSynonyms($synonyms)
                ->setSource(isset($entry['source']) ? (string) $entry['source'] : null)
                ->setTermVersion(isset($entry['term_version']) ? (string) $entry['term_version'] : null);

            $this->em->persist($term);
            $created++;
        }

        $this->em->flush();

        $io->success('Seed vocabulary loaded.');
        $io->table(
            ['Metric', 'Count'],
            [
                ['Terms in file', (string) count($terms)],
                ['Created', (string) $created],
                ['Skipped (already present)', (string) $skipped],
            ],
        );

        return Command::SUCCESS;
    }
}
