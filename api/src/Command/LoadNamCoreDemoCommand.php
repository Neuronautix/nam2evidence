<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\NamCore\AnalysisScript;
use App\Entity\NamCore\Assay;
use App\Entity\NamCore\BiologicalSystem;
use App\Entity\NamCore\CellSource;
use App\Entity\NamCore\Device;
use App\Entity\NamCore\Donor;
use App\Entity\NamCore\EndpointMeasurement;
use App\Entity\NamCore\Exposure;
use App\Entity\NamCore\OntologyMapping;
use App\Entity\NamCore\OntologyTerm;
use App\Entity\NamCore\Platform;
use App\Entity\NamCore\ProvenanceActivity;
use App\Entity\NamCore\QCResult;
use App\Entity\NamCore\RawDataFile;
use App\Entity\NamCore\Sample;
use App\Entity\ContextOfUseCard;
use App\Entity\Project;
use App\Service\NamCore\EndpointMeasurementImporter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Seeds the NAM-CORE data-standardization layer for the existing COU-HEP-001
 * demo project (run `app:load-demo-data` first). Idempotent: existing NAM-CORE
 * rows for the project are purged and recreated.
 *
 * The "before" state deliberately carries the blockers described in demo/README.md:
 * a missing unit, an unmapped endpoint, a missing donor passage, and a
 * measurement lacking raw-file provenance. Pass `--corrected` to load the
 * resolved state instead.
 */
#[AsCommand(name: 'app:load-namcore-demo', description: 'Seed the NAM-CORE layer (endpoints, ontology, provenance) for the demo project.')]
final class LoadNamCoreDemoCommand extends Command
{
    private const COU_ID = 'COU-HEP-001';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly EndpointMeasurementImporter $importer,
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('corrected', null, InputOption::VALUE_NONE, 'Load the corrected (all-blockers-resolved) endpoint dataset.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $corrected = (bool) $input->getOption('corrected');
        $io->title('Seeding NAM-CORE demo layer (' . ($corrected ? 'corrected' : 'before') . ' state)');

        $cou = $this->em->getRepository(ContextOfUseCard::class)->findOneBy(['couId' => self::COU_ID]);
        if ($cou === null) {
            $io->error('Demo project not found. Run app:load-demo-data first.');
            return Command::FAILURE;
        }
        $project = $cou->getProject();

        $this->purge($project);

        // Donors — DONOR-02 intentionally has no passage number (before state).
        $donor1 = (new Donor())->setProject($project)->setDonorCode('DONOR-01')->setLabel('DONOR-01')
            ->setSpeciesLabel('Homo sapiens')->setSpeciesOntologyIri('http://purl.obolibrary.org/obo/NCBITaxon_9606')
            ->setSex('male')->setPassageNumber('P4');
        $donor2 = (new Donor())->setProject($project)->setDonorCode('DONOR-02')->setLabel('DONOR-02')
            ->setSpeciesLabel('Homo sapiens')->setSpeciesOntologyIri('http://purl.obolibrary.org/obo/NCBITaxon_9606')
            ->setSex('female');
        if ($corrected) {
            $donor2->setPassageNumber('P4');
        }
        $this->em->persist($donor1);
        $this->em->persist($donor2);

        $cellSource = (new CellSource())->setProject($project)->setLabel('iPSC-hep-line-A')
            ->setSourceType('ipsc')->setVendor('STEMCELL Technologies / in-house')
            ->setCellTypeLabel('hepatocyte-like cell')->setCellTypeOntologyIri('http://purl.obolibrary.org/obo/CL_0002310')
            ->setDifferentiationProtocol('SOP-ORGNOID-005 v1.4 hepatic differentiation, 21 days')
            ->setDonor($donor1);
        $this->em->persist($cellSource);

        $bioSystem = (new BiologicalSystem())->setProject($project)->setLabel('iPSC-derived liver organoid')
            ->setModelSystemType('organoid')->setSpeciesLabel('Homo sapiens')
            ->setSpeciesOntologyIri('http://purl.obolibrary.org/obo/NCBITaxon_9606')
            ->setAnatomyLabel('liver')->setAnatomyOntologyIri('http://purl.obolibrary.org/obo/UBERON_0002107')
            ->setCellTypeLabel('hepatocyte-like cell')->setCellTypeOntologyIri('http://purl.obolibrary.org/obo/CL_0002310')
            ->setCellSource($cellSource)
            ->setDifferentiationProtocol('SOP-ORGNOID-005 v1.4');
        $this->em->persist($bioSystem);

        $platform = (new Platform())->setProject($project)->setLabel('EnVision plate reader platform')->setPlatformType('plate reader')->setVendor('PerkinElmer');
        $this->em->persist($platform);
        $device = (new Device())->setProject($project)->setLabel('plate-reader-01')->setDeviceType('multilabel plate reader')
            ->setVendor('PerkinElmer')->setModel('EnVision 2105')->setPlatform($platform);
        $this->em->persist($device);

        // Assays (label matches the CSV "assay" column for importer resolution).
        $assayDefs = [
            ['ATP viability', 'cell viability', 'CellTiter-Glo 3D', 'luminescence', 'http://purl.obolibrary.org/obo/OBI_0002119'],
            ['LDH release', 'cytotoxicity', 'CytoTox-ONE', 'fluorescence', 'http://purl.obolibrary.org/obo/OBI_0002994'],
            ['MMP JC-1', 'mitochondrial function', 'JC-1', 'ratiometric fluorescence', null],
            ['Bile acid LC-MS', 'targeted metabolomics', 'LC-MS/MS', 'peak area', null],
            ['ROS CellROX', 'oxidative stress', 'CellROX Green', 'fluorescence', null],
            ['Novel oxidative panel', 'exploratory', 'lipid peroxidation panel', 'fluorescence', null],
        ];
        foreach ($assayDefs as [$label, $type, $method, $readout, $iri]) {
            $a = (new Assay())->setProject($project)->setLabel($label)->setAssayType($type)->setMethod($method)->setReadout($readout);
            if ($iri !== null) { $a->setTechnologyOntologyIri($iri); }
            $this->em->persist($a);
        }

        // Exposures (testArticle matches the CSV "exposure" column).
        $exposureDefs = [
            ['Acetaminophen', 'acetaminophen', 'http://purl.obolibrary.org/obo/CHEBI_46195', 10000.0, 'µM', 24.0, 'h'],
            ['CX-4471', 'CX-4471 (kinase inhibitor)', 'http://purl.obolibrary.org/obo/CHEBI_38637', 30.0, 'µM', 72.0, 'h'],
            ['Metformin', 'metformin', 'http://purl.obolibrary.org/obo/CHEBI_6801', 10000.0, 'µM', 24.0, 'h'],
        ];
        foreach ($exposureDefs as [$label, $article, $iri, $conc, $unit, $tp, $tpUnit]) {
            $e = (new Exposure())->setProject($project)->setLabel($label)->setTestArticle($label)
                ->setTestArticleOntologyIri($iri)->setConcentrationValue($conc)->setConcentrationUnit($unit)
                ->setTimepointValue($tp)->setTimepointUnit($tpUnit)->setVehicle('DMSO 0.1%');
            $this->em->persist($e);
        }

        // Samples (sampleCode matches CSV "sample" column).
        foreach (range(1, 12) as $n) {
            $code = sprintf('S-%03d', $n);
            $s = (new Sample())->setProject($project)->setLabel($code)->setSampleCode($code)
                ->setBatchId($n <= 5 || $n === 11 ? 'B1' : 'B2')->setReplicateId('R' . (($n % 3) + 1))
                ->setBiologicalSystem($bioSystem);
            $this->em->persist($s);
        }

        // Raw data files (fileName matches CSV "raw_file"). Note: cellrox_run2.csv and
        // lipid panel file only exist here so that provenance links resolve; the before
        // state deliberately omits the raw file for the exploratory endpoint row.
        $rawFiles = ['celltiterglo_run1.csv', 'celltiterglo_run2.csv', 'cytotox_run1.csv', 'jc1_run2.csv', 'lcms_run2.csv', 'cellrox_run2.csv'];
        foreach ($rawFiles as $fn) {
            $r = (new RawDataFile())->setProject($project)->setLabel($fn)->setFileName($fn)
                ->setChecksum(substr(hash('sha256', $fn), 0, 32))->setChecksumAlgorithm('sha256')
                ->setSourceSystem('instrument_export');
            $this->em->persist($r);
        }

        $script = (new AnalysisScript())->setProject($project)->setLabel('cx4471-hep-analysis')
            ->setName('cx4471-hep-analysis')->setRepositoryUrl('github.com/neuronautix/cx4471-hep-analysis')
            ->setReference('v1.2 (commit 7f3a1c9)')->setLanguage('R/Python')->setScriptVersion('v1.2');
        $this->em->persist($script);
        $activity = (new ProvenanceActivity())->setProject($project)->setLabel('Concentration-response analysis')
            ->setActivityType('analysis')->setSoftwareName('GraphPad Prism 10 / R 4.3')->setSoftwareVersion('v1.2')
            ->setScriptReference('github.com/neuronautix/cx4471-hep-analysis@v1.2')->setAnalysisScript($script)
            ->setAgentName('Computational toxicology analyst')->setAgentRole('analyst');
        $this->em->persist($activity);

        $this->em->flush();

        // Import endpoint measurements via the real importer.
        $csvFile = $this->projectDir . '/../demo/' . ($corrected ? 'endpoint_measurements_corrected.csv' : 'endpoint_measurements_raw.csv');
        $imported = 0;
        if (is_readable($csvFile)) {
            $csv = (string) file_get_contents($csvFile);
            $mapping = $this->importer->preview($csv)['suggested_mapping'];
            $summary = $this->importer->import($project, $csv, $mapping);
            $imported = (int) $summary['imported'];
            // Link the exploratory analysis activity to rows that resolved a raw file
            // so provenance passes where a raw file exists.
            $this->linkAnalysisActivity($project, $activity);
        } else {
            $io->warning('Endpoint CSV not found at ' . $csvFile);
        }

        // Ontology mappings from the seed file.
        $mapCount = $this->seedOntologyMappings($project, $corrected);

        $this->em->flush();

        $io->success(sprintf(
            'NAM-CORE demo seeded: 2 donors, 1 biological system, 6 assays, 3 exposures, 12 samples, %d endpoint measurements, %d ontology mappings.',
            $imported,
            $mapCount,
        ));
        $io->note($corrected
            ? 'Corrected state — semantic validation should conform and the export gate should open.'
            : 'Before state — expect blockers: missing unit, unmapped endpoint, missing donor passage, missing provenance, pending claims.');

        return Command::SUCCESS;
    }

    private function linkAnalysisActivity(Project $project, ProvenanceActivity $activity): void
    {
        $measurements = $this->em->getRepository(EndpointMeasurement::class)->findBy(['project' => $project]);
        foreach ($measurements as $m) {
            if ($m->getRawDataFile() !== null && $m->getAnalysisActivity() === null) {
                $m->setAnalysisActivity($activity);
            }
        }
    }

    private function seedOntologyMappings(Project $project, bool $corrected): int
    {
        $seedFile = $this->projectDir . '/../demo/ontology_mapping_seed.json';
        if (!is_readable($seedFile)) {
            return 0;
        }
        $seed = json_decode((string) file_get_contents($seedFile), true);
        if (!is_array($seed) || !isset($seed['mappings'])) {
            return 0;
        }
        $count = 0;
        foreach ($seed['mappings'] as $def) {
            $mapping = (new OntologyMapping())->setProject($project)
                ->setSourceEntityType((string) $def['source_entity_type'])
                ->setSourceValue((string) $def['source_value'])
                ->setMandatory((bool) ($def['mandatory'] ?? false));

            $curie = $def['suggested_curie'] ?? null;
            if ($curie !== null) {
                $term = $this->em->getRepository(OntologyTerm::class)->findOneBy(['curie' => $curie]);
                if ($term !== null) {
                    $mapping->setOntologyTerm($term);
                    if ($corrected) {
                        $mapping->setMappingStatus(OntologyMapping::STATUS_APPROVED)->setMappingConfidence(1.0)->setReviewedBy('demo-reviewer');
                    } else {
                        $mapping->setMappingStatus(OntologyMapping::STATUS_SUGGESTED)->setMappingConfidence(0.6);
                    }
                }
            } elseif ($corrected) {
                // In the corrected state, the previously-unmapped endpoint gets an internal NAM term.
                $term = $this->ensureInternalTerm($def['source_value']);
                $mapping->setOntologyTerm($term)->setMappingStatus(OntologyMapping::STATUS_APPROVED)
                    ->setMappingConfidence(1.0)->setReviewedBy('demo-reviewer');
            }

            $this->em->persist($mapping);
            $count++;
        }
        return $count;
    }

    private function ensureInternalTerm(string $sourceValue): OntologyTerm
    {
        $curie = 'NAM:' . strtoupper(preg_replace('/[^a-z0-9]+/i', '_', $sourceValue) ?? 'TERM');
        $existing = $this->em->getRepository(OntologyTerm::class)->findOneBy(['curie' => $curie]);
        if ($existing !== null) {
            return $existing;
        }
        $term = (new OntologyTerm())->setLabel($sourceValue)->setOntologyPrefix('NAM')->setCurie($curie)
            ->setDefinition('Internal NAM endpoint vocabulary term (POC).')->setSource('internal NAM vocabulary');
        $this->em->persist($term);
        return $term;
    }

    private function purge(Project $project): void
    {
        foreach ([EndpointMeasurement::class, QCResult::class, Sample::class, Exposure::class, Assay::class,
                  BiologicalSystem::class, CellSource::class, Donor::class, Device::class, Platform::class,
                  ProvenanceActivity::class, AnalysisScript::class, RawDataFile::class, OntologyMapping::class] as $class) {
            foreach ($this->em->getRepository($class)->findBy(['project' => $project]) as $row) {
                $this->em->remove($row);
            }
            $this->em->flush();
        }
    }
}
