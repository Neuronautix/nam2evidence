<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\ClaimEdge;
use App\Entity\ClaimNode;
use App\Entity\ContextOfUseCard;
use App\Entity\ECTDMapping;
use App\Entity\EvidenceItem;
use App\Entity\NAMStudy;
use App\Entity\Project;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Loads (or reloads) the canonical demo project COU-HEP-001 (CX-4471 / liver
 * organoid hepatotoxicity) used by the frontend in lib/demoData.ts.
 *
 * This command is idempotent: if the demo project (identified by
 * COU-HEP-001 / NAM-STUDY-001) already exists it will be removed together with
 * its dependent rows, then recreated from scratch.
 *
 * Usage:
 *   bin/console app:load-demo-data           (interactive — prompts before delete)
 *   bin/console app:load-demo-data --force   (non-interactive)
 */
#[AsCommand(
    name: 'app:load-demo-data',
    description: 'Hydrate the database with the canonical COU-HEP-001 demo project (matches frontend demoData.ts).',
)]
final class LoadDemoDataCommand extends Command
{
    /** Stable identifiers — must match frontend/src/lib/demoData.ts */
    private const COU_ID = 'COU-HEP-001';
    private const STUDY_ID = 'NAM-STUDY-001';
    private const PROJECT_NAME = 'Hepatotoxicity Liability Assessment – CompoundX';

    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'force',
            'f',
            InputOption::VALUE_NONE,
            'Skip the confirmation prompt and reload demo data unconditionally.',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Loading demo data: COU-HEP-001 (CX-4471 / liver organoid hepatotoxicity)');

        $existingCou = $this->em->getRepository(ContextOfUseCard::class)
            ->findOneBy(['couId' => self::COU_ID]);

        if ($existingCou !== null && !$input->getOption('force')) {
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion(
                sprintf(
                    'Demo project (COU %s) already exists. Delete and reload? [y/N] ',
                    self::COU_ID,
                ),
                false,
            );
            if (!$helper->ask($input, $output, $question)) {
                $io->warning('Aborted by user.');
                return Command::SUCCESS;
            }
        }

        if ($existingCou !== null) {
            $io->section('Removing existing demo project');
            $this->purgeDemoProject($existingCou->getProject());
            $io->writeln(' <info>removed</info>');
        }

        $io->section('Hydrating new demo project');

        $project = $this->createProject();
        $this->em->persist($project);

        $cou = $this->createCou($project);
        $this->em->persist($cou);

        $study = $this->createStudy($project, $cou);
        $this->em->persist($study);

        $evidenceCount = 0;
        foreach ($this->buildEvidenceItems($study) as $evidence) {
            $this->em->persist($evidence);
            $evidenceCount++;
        }

        /** @var array<string, ClaimNode> $claimsByCode */
        $claimsByCode = [];
        foreach ($this->buildClaims($project, $cou) as $claim) {
            $this->em->persist($claim);
            $claimsByCode[$claim->getClaimId()] = $claim;
        }

        // Wire parent-claim relationships now that all claims exist.
        $this->wireParentClaims($claimsByCode);

        $edgeCount = 0;
        foreach ($this->buildClaimEdges($claimsByCode) as $edge) {
            $this->em->persist($edge);
            $edgeCount++;
        }

        $mappingCount = 0;
        foreach ($this->buildEctdMappings($study, $claimsByCode) as $mapping) {
            $this->em->persist($mapping);
            $mappingCount++;
        }

        $this->em->flush();

        $io->success('Demo data loaded.');

        $table = new Table($output);
        $table->setHeaders(['Entity', 'Identifier', 'Count']);
        $table->setRows([
            ['Project', $project->getName(), '1'],
            ['ContextOfUseCard', self::COU_ID, '1'],
            ['NAMStudy', self::STUDY_ID, '1'],
            ['EvidenceItem', 'EVID-001..EVID-015', (string) $evidenceCount],
            ['ClaimNode', 'CLAIM-001..CLAIM-005', (string) count($claimsByCode)],
            ['ClaimEdge', '(WoE graph)', (string) $edgeCount],
            ['ECTDMapping', 'ECTD-MAP-001..ECTD-MAP-005', (string) $mappingCount],
        ]);
        $table->render();

        return Command::SUCCESS;
    }

    /**
     * Delete the demo project and every row that references it. Doctrine's
     * cascade=remove on Project→ContextOfUseCard handles COU rows; the rest
     * we delete explicitly because no cascade is declared.
     */
    private function purgeDemoProject(Project $project): void
    {
        $studies = $this->em->getRepository(NAMStudy::class)->findBy(['project' => $project]);
        foreach ($studies as $study) {
            // EvidenceItems
            foreach ($this->em->getRepository(EvidenceItem::class)->findBy(['study' => $study]) as $ev) {
                $this->em->remove($ev);
            }
            // ECTDMappings linked via study
            foreach ($this->em->getRepository(ECTDMapping::class)->findBy(['study' => $study]) as $m) {
                $this->em->remove($m);
            }
        }

        $claims = $this->em->getRepository(ClaimNode::class)->findBy(['project' => $project]);
        foreach ($claims as $claim) {
            // ECTDMappings linked via claim
            foreach ($this->em->getRepository(ECTDMapping::class)->findBy(['claim' => $claim]) as $m) {
                $this->em->remove($m);
            }
            // ClaimEdges (CASCADE on join column will fire on flush, but be explicit)
            foreach ($this->em->getRepository(ClaimEdge::class)->findBy(['fromClaim' => $claim]) as $e) {
                $this->em->remove($e);
            }
            foreach ($this->em->getRepository(ClaimEdge::class)->findBy(['toClaim' => $claim]) as $e) {
                $this->em->remove($e);
            }
        }
        // Flush deletions of edges/mappings before claims to avoid FK violations.
        $this->em->flush();

        foreach ($claims as $claim) {
            $this->em->remove($claim);
        }
        foreach ($studies as $study) {
            $this->em->remove($study);
        }
        // Project's cascade=remove handles ContextOfUseCard rows.
        $this->em->remove($project);
        $this->em->flush();
    }

    private function createProject(): Project
    {
        return (new Project())
            ->setName(self::PROJECT_NAME)
            ->setDescription(
                'NAM-derived nonclinical evidence package supporting IND enabling studies for '
                . 'CompoundX, a small-molecule kinase inhibitor with suspected hepatotoxic liability.',
            )
            ->setDrugName('CompoundX (CX-4471)')
            ->setSponsor('Neuronautix Therapeutics')
            ->setReviewStatus('human_review_required');
    }

    private function createCou(Project $project): ContextOfUseCard
    {
        return (new ContextOfUseCard())
            ->setCouId(self::COU_ID)
            ->setProject($project)
            ->setNamType('Organoid')
            ->setRegulatoryQuestion(
                'Does CompoundX (CX-4471) cause hepatocellular injury at pharmacologically relevant '
                . 'concentrations, and if so, at what exposure multiples relative to projected human Cmax?',
            )
            ->setDrugDevelopmentStage('IND_enabling')
            ->setIntendedUse(
                'Characterise hepatocellular toxicity liability of CX-4471 to inform the design of '
                . 'repeat-dose GLP toxicology studies and support a first-in-human IND safety narrative.',
            )
            ->setDecisionSupported(
                'Selection of starting dose and dose-escalation schedule for Phase I; identification '
                . 'of liver as a target organ for enhanced monitoring.',
            )
            ->setBiologicalDomain('Hepatotoxicity / Drug-Induced Liver Injury (DILI)')
            ->setEndpointClass('Cytotoxicity, cholestasis, mitochondrial impairment, bile-acid accumulation')
            ->setPopulationRelevance(
                'Human iPSC-derived hepatocyte organoids from three donor lines (male/female, '
                . 'CYP2C9*1/*3 polymorphism represented) to capture inter-individual variability.',
            )
            ->setLimitations([
                'Organoid system lacks sinusoidal blood flow and Kupffer-cell mediated immune activation.',
                'Long-term (>28 day) cultures show phenotypic drift; findings limited to acute and sub-chronic windows.',
                'Biliary canalicular efflux transport may be underrepresented in batch lots used.',
                'Oxidative metabolism comparable to primary hepatocytes only for CYP3A4/2C9; CYP1A2 activity low.',
            ])
            ->setAcceptanceCriteria([
                'Z’ factor ≥0.5 for each cytotoxicity endpoint across three independent runs.',
                'Bile acid accumulation assay CV ≤25% intra-assay, ≤30% inter-assay.',
                'Reference hepatotoxicant panel (n=12 compounds): sensitivity ≥75%, specificity ≥70%.',
                'Human Cmax multiple coverage ≥30× achieved at highest test concentration.',
            ])
            ->setRegulatoryConfidenceLevel('supportive')
            ->setReviewStatus('draft')
            ->setVersion('1.2');
    }

    private function createStudy(Project $project, ContextOfUseCard $cou): NAMStudy
    {
        return (new NAMStudy())
            ->setStudyId(self::STUDY_ID)
            ->setProject($project)
            ->setContextOfUse($cou)
            ->setTitle('CX-4471 Hepatotoxicity Assessment in iPSC-Derived Liver Organoids (NAMO-aligned)')
            ->setModelSystem([
                'namo_class' => 'Organoid',
                'species' => 'Homo sapiens',
                'cell_type' => 'iPSC-derived hepatocyte-like cells',
                'tissue_origin' => 'Liver (hepatocellular)',
                'culture_conditions' => '3D self-assembling organoid; HepatiCult Organoid Growth Medium; '
                    . '5% CO₂, 37 °C; Matrigel-embedded domes',
                'vendor' => 'STEMCELL Technologies / in-house differentiation',
                'catalog_number' => 'HepatiCult-OGM-hum',
                'passage_number' => 'P3–P8',
                'maturity_indicators' => [
                    'Albumin secretion >200 ng/mL/day',
                    'CYP3A4 induction ratio ≥5× by rifampicin',
                    'HNF4α nuclear localisation >80% cells',
                    'Tight junction formation (ZO-1 immunofluorescence)',
                ],
            ])
            ->setExperimentalDesign([
                'study_type' => 'Concentration-response, multi-endpoint',
                'concentrations_uM' => [0.1, 0.3, 1, 3, 10, 30, 100],
                'vehicle' => 'DMSO (0.1% final)',
                'treatment_duration_hours' => [24, 72],
                'replicates' => 'n=3 biological replicates × 3 technical replicates',
                'reference_compounds' => [
                    ['name' => 'Acetaminophen', 'class' => 'Hepatotoxicant', 'expected' => 'positive'],
                    ['name' => 'Fialuridine', 'class' => 'Mitochondrial toxicant', 'expected' => 'positive'],
                    ['name' => 'Troglitazone', 'class' => 'DILI reference', 'expected' => 'positive'],
                    ['name' => 'Metformin', 'class' => 'Non-hepatotoxic control', 'expected' => 'negative'],
                ],
            ])
            ->setAssayMetadata([
                'primary_endpoints' => [
                    [
                        'name' => 'ATP viability',
                        'method' => 'CellTiter-Glo 3D',
                        'readout' => 'luminescence',
                        'unit' => '% vehicle control',
                    ],
                    [
                        'name' => 'LDH release',
                        'method' => 'CytoTox-ONE',
                        'readout' => 'fluorescence',
                        'unit' => '% max lysis',
                    ],
                    [
                        'name' => 'Mitochondrial membrane potential',
                        'method' => 'JC-1 dye',
                        'readout' => 'ratiometric fluorescence',
                        'unit' => 'J-aggregates:J-monomers ratio',
                    ],
                    [
                        'name' => 'Intracellular bile acid accumulation',
                        'method' => 'LC-MS/MS (targeted)',
                        'readout' => 'peak area ratio',
                        'unit' => 'pmol per mg protein',
                    ],
                    [
                        'name' => 'ROS generation',
                        'method' => 'CellROX Green',
                        'readout' => 'fluorescence',
                        'unit' => 'fold over vehicle',
                    ],
                ],
                'instrument' => 'EnVision multilabel plate reader; Agilent 6500 QTOF',
                'software' => 'TIBCO Spotfire 12.1, GraphPad Prism 10',
            ])
            ->setDataOutputs([
                'tc50_atp_uM' => ['value' => 18.4, 'ci95' => [14.2, 23.8], 'unit' => 'µM'],
                'tc50_ldh_uM' => ['value' => 22.1, 'ci95' => [17.5, 28.9], 'unit' => 'µM'],
                'noael_uM' => ['value' => 3.0, 'unit' => 'µM'],
                'human_cmax_uM' => 0.6,
                'safety_multiple_noael' => 5.0,
                'safety_multiple_tc50' => 30.7,
                'bile_acid_increase_fold' => 3.2,
                'mmp_decrease_pct_at_10uM' => 41,
            ])
            ->setProvenance([
                'study_director' => 'Dr. A. Okonkwo',
                'facility' => 'Neuronautix In Vitro Pharmacology, Cambridge UK',
                'study_initiation_date' => '2026-02-01',
                'study_completion_date' => '2026-03-28',
                'raw_data_location' => 'LIMS-NNX/studies/NAM-STUDY-001',
                'analysis_script' => 'github.com/neuronautix/cx4471-hep-analysis/v1.2',
                'sop_references' => ['SOP-IVTOX-003 v2.1', 'SOP-ORGNOID-005 v1.4'],
                'audit_trail' => 'ELN entries NNX-2026-0201 through NNX-2026-0328',
            ]);
    }

    /**
     * @return list<EvidenceItem>
     */
    private function buildEvidenceItems(NAMStudy $study): array
    {
        $rows = [
            // Analytical Validity
            [
                'EVID-001', 'analytical_validity',
                'Is the assay endpoint (ATP viability) analytically validated with Z’ ≥ 0.5?',
                'Assay validation data', 'met',
                "Z' = 0.72 (mean of 3 runs). Meets acceptance criterion of ≥0.5.",
                'Table 2, Study Report NAM-STUDY-001',
            ],
            [
                'EVID-002', 'analytical_validity',
                'Are all analytical readouts within dynamic range and free from interference at tested concentrations?',
                'Interference check panel', 'met',
                'Fluorescence interference excluded by counter-screen; compound colour-quench not detected.',
                'Appendix B, NAM-STUDY-001',
            ],
            // Technical Reproducibility
            [
                'EVID-003', 'technical_reproducibility',
                'Is the coefficient of variation (CV) ≤25% intra-assay for all primary endpoints?',
                'Replicate statistics', 'met',
                'CV range 8–22% across ATP, LDH, MMP endpoints. Bile acid LC-MS/MS CV 18% intra-assay.',
                'Table 3',
            ],
            [
                'EVID-004', 'technical_reproducibility',
                'Do results replicate across three independent biological replicates?',
                'Inter-replicate analysis', 'met',
                'TC50 ATP values: 17.8, 18.1, 19.3 µM across runs. Geometric CV = 4.4%.',
                'Figure 3A',
            ],
            // Biological Relevance
            [
                'EVID-005', 'biological_relevance',
                'Does the model express key hepatic transporters (OATP1B1, BSEP, MRP2) relevant to DILI?',
                'Transporter expression (RT-PCR + immunostaining)', 'partial',
                'OATP1B1 and MRP2 expressed. BSEP expression ~30% of primary hepatocyte level – '
                . 'reduced canalicular efflux capacity is a documented limitation.',
                'Supplementary Figure S2',
            ],
            [
                'EVID-006', 'biological_relevance',
                'Does the organoid system recapitulate key metabolic CYP450 activities relevant to CX-4471 metabolism?',
                'CYP induction/activity profiling', 'partial',
                'CYP3A4 and CYP2C9 activity comparable to cryopreserved hepatocytes. CYP1A2 activity '
                . '<20% – limits detection of reactive metabolite-driven toxicity via this pathway.',
                'Table S1',
            ],
            // Reference Compound Performance
            [
                'EVID-007', 'reference_compound_performance',
                'Does the model correctly classify the 12-compound reference hepatotoxicant panel?',
                'Reference panel concordance', 'met',
                'Sensitivity 83% (10/12 positives detected), specificity 75% (3/4 negatives). '
                . 'Meets pre-specified acceptance criteria.',
                'Table 4, Supplementary Table S3',
            ],
            [
                'EVID-008', 'reference_compound_performance',
                'Are positive control responses within expected historical ranges in each run?',
                'Positive control tracking', 'met',
                'Acetaminophen TC50 1.8–2.3 mM (historical 1.5–2.8 mM). Fialuridine MMP50 within '
                . '±1 SD historical range.',
                'Control charts, Appendix C',
            ],
            // Exposure Relevance
            [
                'EVID-009', 'exposure_relevance',
                'Does the highest tested concentration achieve ≥30× the projected human Cmax?',
                'Exposure multiple analysis', 'met',
                'Highest concentration 100 µM; projected human Cmax 0.6 µM (PK simulation). '
                . 'Coverage = 167×. TC50 safety multiple = 30.7×.',
                'Section 4.3, Exposure Assessment Memo EXP-CX4471-001',
            ],
            [
                'EVID-010', 'exposure_relevance',
                'Is free (unbound) intracellular concentration considered in toxicity interpretation?',
                'Protein binding / bioavailability correction', 'partial',
                'Plasma protein binding 94% (fu,plasma = 0.06). Intracellular partitioning estimated '
                . 'from log P; in vitro free fraction not directly measured in organoid matrix.',
                'Memo EXP-CX4471-001, Section 3.2',
            ],
            // Data Integrity
            [
                'EVID-011', 'data_integrity',
                'Are raw data retained in a compliant electronic laboratory notebook with audit trail?',
                'ELN / LIMS documentation', 'met',
                'All raw instrument files and plate layouts archived in LIMS-NNX with 21 CFR Part '
                . '11-aligned audit trail.',
                'Provenance section, NAM-STUDY-001',
            ],
            [
                'EVID-012', 'data_integrity',
                'Is analysis pipeline version-controlled and reproducible?',
                'Computational reproducibility', 'met',
                'Analysis scripts committed to Git (tagged v1.2); Docker image archived for '
                . 'computational reproducibility.',
                'Provenance section, NAM-STUDY-001',
            ],
            // Limitation Analysis
            [
                'EVID-013', 'limitation_analysis',
                'Are known model limitations clearly documented and their impact on interpretation assessed?',
                'Limitation register', 'met',
                'Four limitations documented in COU-HEP-001. BSEP underexpression and CYP1A2 gap '
                . 'flagged as interpretive caveats in study report.',
                'COU-HEP-001 v1.2; Study Report Section 7',
            ],
            // Regulatory Alignment
            [
                'EVID-014', 'regulatory_alignment',
                'Is the study design aligned with FDA/EMA guidance on in vitro hepatotoxicity assessment?',
                'Regulatory guidance mapping', 'met',
                'Aligned with ICH S7A principles, FDA 2023 DILI draft guidance, and EMA non-clinical '
                . 'guideline concepts. Multi-endpoint design reflects best practice.',
                'Regulatory Alignment Table, Study Report Section 2',
            ],
            [
                'EVID-015', 'regulatory_alignment',
                'Is NAMO-compliant metadata captured to allow reproducibility and regulatory traceability?',
                'NAMO metadata completeness audit', 'met',
                'NAMO-required fields for Organoid class all populated. Provenance, model system '
                . 'ontology terms, and assay metadata conform to NAMO v1.3 schema.',
                'NAMO Metadata Export, NAM-STUDY-001',
            ],
        ];

        $items = [];
        foreach ($rows as [$evidenceId, $domain, $question, $type, $status, $notes, $supportingData]) {
            $items[] = (new EvidenceItem())
                ->setEvidenceId($evidenceId)
                ->setStudy($study)
                ->setDomain($domain)
                ->setQuestion($question)
                ->setEvidenceType($type)
                ->setStatus($status)
                ->setNotes($notes)
                ->setSupportingData($supportingData);
        }

        return $items;
    }

    /**
     * @return list<ClaimNode>
     */
    private function buildClaims(Project $project, ContextOfUseCard $cou): array
    {
        $defs = [
            [
                'CLAIM-001',
                'CX-4471 does not cause hepatocellular cytotoxicity at pharmacologically relevant '
                . 'exposures (≤3 µM; 5× human Cmax).',
                'empirical', 'supportive',
                ['EVID-001', 'EVID-003', 'EVID-004', 'EVID-009'], [],
                ['BSEP underexpression may underestimate cholestatic potential at therapeutic exposures.'],
                ['4.2.3.7.3', '2.6.2'],
                null,
            ],
            [
                'CLAIM-002',
                'At ≥10 µM (16.7× human Cmax), CX-4471 causes mitochondrial membrane potential '
                . 'disruption (41% decrease) and bile acid accumulation (3.2× increase).',
                'empirical', 'supportive',
                ['EVID-001', 'EVID-002', 'EVID-009', 'EVID-010'], [],
                ['In vitro free fraction not directly measured; intracellular exposure is estimated.'],
                ['4.2.3.7.3', '4.2.3.2'],
                'CLAIM-001',
            ],
            [
                'CLAIM-003',
                'The organoid model used has sufficient biological relevance for hepatotoxicity '
                . 'hazard identification for CX-4471, with the documented limitation of reduced '
                . 'BSEP expression.',
                'mechanistic', 'supportive',
                ['EVID-005', 'EVID-006', 'EVID-007', 'EVID-008'], ['EVID-005'],
                [
                    'BSEP expression ~30% of primary hepatocyte level.',
                    'CYP1A2 activity limited – reactive metabolite pathways via CYP1A2 not adequately covered.',
                ],
                ['4.2.3.7.3'],
                null,
            ],
            [
                'CLAIM-004',
                'The hepatotoxicity evidence package supports identification of the liver as a '
                . 'target organ for enhanced clinical monitoring in Phase I, but does not replace '
                . 'GLP repeat-dose toxicology studies.',
                'predictive', 'decision_informing',
                ['EVID-011', 'EVID-012', 'EVID-013', 'EVID-014', 'EVID-015'], [],
                [
                    'Evidence is limited to acute and sub-chronic in vitro exposures.',
                    'Immunological mechanisms of DILI not captured.',
                ],
                ['2.6.2', '2.6.6', '4.2.3.7.3'],
                'CLAIM-001',
            ],
            [
                'CLAIM-005',
                'The NOAEL from the organoid system (3 µM; 5× human Cmax projected) is consistent '
                . 'with a conservative starting dose and supports the proposed Phase I '
                . 'dose-escalation design.',
                'comparative', 'exploratory',
                ['EVID-009', 'EVID-010'], [],
                [
                    'In vitro NOAEL is not a NOAEL in the regulatory sense; direct translation to '
                    . 'in vivo dosing requires bridging toxicokinetic data.',
                    'Allometric scaling and hepatic first-pass not modelled.',
                ],
                ['2.6.6', '4.2.3.2'],
                'CLAIM-004',
            ],
        ];

        $claims = [];
        foreach ($defs as [$claimId, $text, $type, $confidence, $supporting, $contradictory, $limitations, $sections, $parentCode]) {
            $claim = (new ClaimNode())
                ->setClaimId($claimId)
                ->setProject($project)
                ->setClaimText($text)
                ->setNodeType('claim')
                ->setClaimType($type)
                ->setContextOfUse($cou)
                ->setConfidence($confidence)
                ->setSupportingEvidence($supporting)
                ->setContradictoryEvidence($contradictory)
                ->setLimitations($limitations)
                ->setEctdTargetSections($sections)
                ->setReviewStatus('human_review_required');

            // Stash parent code on a transient property via a closure key — we can't add fields,
            // so store it in a side-channel array keyed by claimId in wireParentClaims().
            $claims[] = $claim;
            $this->parentCodeQueue[$claimId] = $parentCode;
        }

        return $claims;
    }

    /** @var array<string, string|null> */
    private array $parentCodeQueue = [];

    /**
     * @param array<string, ClaimNode> $claimsByCode
     */
    private function wireParentClaims(array $claimsByCode): void
    {
        foreach ($this->parentCodeQueue as $childCode => $parentCode) {
            if ($parentCode === null) {
                continue;
            }
            if (!isset($claimsByCode[$childCode], $claimsByCode[$parentCode])) {
                continue;
            }
            $claimsByCode[$childCode]->setParentClaim($claimsByCode[$parentCode]);
        }
    }

    /**
     * @param array<string, ClaimNode> $claimsByCode
     * @return list<ClaimEdge>
     */
    private function buildClaimEdges(array $claimsByCode): array
    {
        $edges = [
            ['CLAIM-003', 'CLAIM-001', 'supports'],
            ['CLAIM-001', 'CLAIM-004', 'supports'],
            ['CLAIM-002', 'CLAIM-004', 'supports'],
            ['CLAIM-004', 'CLAIM-005', 'supports'],
            ['CLAIM-003', 'CLAIM-002', 'qualifies'],
        ];

        $out = [];
        foreach ($edges as [$from, $to, $rel]) {
            $out[] = (new ClaimEdge())
                ->setFromClaim($claimsByCode[$from])
                ->setToClaim($claimsByCode[$to])
                ->setRelationship($rel);
        }
        return $out;
    }

    /**
     * @param array<string, ClaimNode> $claimsByCode
     * @return list<ECTDMapping>
     */
    private function buildEctdMappings(NAMStudy $study, array $claimsByCode): array
    {
        $defs = [
            [
                'ECTD-MAP-001', $study, $claimsByCode['CLAIM-001'] ?? null,
                'In vitro cytotoxicity (organoid)', '4.2.3.7.3', 'Other in vitro studies',
                'Full study report, NAMO metadata export, and validation evidence matrix to be '
                . 'included. Label as "non-GLP, exploratory/supportive" per FDA NDI guidance.',
                'Organoid hepatotoxicity study constitutes an in vitro safety pharmacology / '
                . 'toxicology study; placement in 4.2.3.7.3 is appropriate for non-GLP in vitro '
                . 'studies supplementing the nonclinical overview.',
            ],
            [
                'ECTD-MAP-002', $study, $claimsByCode['CLAIM-002'] ?? null,
                'Mitochondrial liability & cholestasis endpoints', '4.2.3.2', 'Toxicokinetics',
                'Exposure-response data and safety margin calculations (based on projected human '
                . 'Cmax) to be referenced in the TK section to provide context for the observed '
                . 'toxicity thresholds.',
                'Safety multiple calculations link to TK section for proper cross-referencing with '
                . 'in vivo TK data when available.',
            ],
            [
                'ECTD-MAP-003', null, $claimsByCode['CLAIM-004'] ?? null,
                'Weight-of-evidence summary', '2.6.6', 'Toxicology Written Summary',
                'Summary of hepatotoxicity evidence weight-of-evidence to be incorporated into the '
                . 'Toxicology Written Summary (2.6.6) as a subsection on special toxicity studies '
                . '/ novel methodologies.',
                'The interpretive claim and confidence assessment belong in the integrated written '
                . 'summary rather than raw data sections.',
            ],
            [
                'ECTD-MAP-004', null, $claimsByCode['CLAIM-004'] ?? null,
                'NAM methodology overview', '2.6.2', 'Pharmacology Written Summary',
                'Brief description of NAM approach, COU framing, and limitations to be included in '
                . 'the Pharmacology Written Summary as a note on novel methodology used to '
                . 'characterise hepatic safety pharmacology.',
                'FDA encourages disclosure of novel nonclinical tools and methods in the written '
                . 'summaries to provide reviewers context.',
            ],
            [
                'ECTD-MAP-005', null, $claimsByCode['CLAIM-003'] ?? null,
                'Validation evidence matrix (CSV)', '4.2.3.7.3',
                'Other in vitro studies – supplementary validation data',
                'Validation evidence matrix (EVID-MATRIX-001) to be included as a supporting file '
                . 'alongside the study report in section 4.2.3.7.3.',
                'Providing the structured validation evidence matrix enables reviewers to assess '
                . 'model fitness-for-purpose without requiring deep familiarity with NAMO ontology.',
            ],
        ];

        $out = [];
        foreach ($defs as [$mappingId, $studyRef, $claimRef, $type, $section, $title, $notes, $justification]) {
            $mapping = (new ECTDMapping())
                ->setMappingId($mappingId)
                ->setStudy($studyRef)
                ->setClaim($claimRef)
                ->setEvidenceType($type)
                ->setEctdSection($section)
                ->setEctdTitle($title)
                ->setNotes($notes)
                ->setJustification($justification);
            $out[] = $mapping;
        }
        return $out;
    }
}
