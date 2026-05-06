import {
  Project,
  ContextOfUseCard,
  NAMStudy,
  EvidenceItem,
  ClaimNode,
  ClaimEdge,
  ECTDMapping,
} from './types';

// ─── Demo Project ──────────────────────────────────────────────────────────────

export const demoProject: Project = {
  id: 'proj-hep-001',
  name: 'Hepatotoxicity Liability Assessment – CompoundX',
  description:
    'NAM-derived nonclinical evidence package supporting IND enabling studies for CompoundX, a small-molecule kinase inhibitor with suspected hepatotoxic liability.',
  drug_name: 'CompoundX (CX-4471)',
  sponsor: 'Neuronautix Therapeutics',
  created_at: '2026-01-15T09:00:00Z',
  updated_at: '2026-05-01T14:32:00Z',
  review_status: 'human_review_required',
};

// ─── Context of Use Card ───────────────────────────────────────────────────────

export const demoCOU: ContextOfUseCard = {
  cou_id: 'COU-HEP-001',
  project_id: 'proj-hep-001',
  nam_type: 'Organoid',
  regulatory_question:
    'Does CompoundX (CX-4471) cause hepatocellular injury at pharmacologically relevant concentrations, and if so, at what exposure multiples relative to projected human Cmax?',
  drug_development_stage: 'IND_enabling',
  intended_use:
    'Characterise hepatocellular toxicity liability of CX-4471 to inform the design of repeat-dose GLP toxicology studies and support a first-in-human IND safety narrative.',
  decision_supported:
    'Selection of starting dose and dose-escalation schedule for Phase I; identification of liver as a target organ for enhanced monitoring.',
  biological_domain: 'Hepatotoxicity / Drug-Induced Liver Injury (DILI)',
  endpoint_class: 'Cytotoxicity, cholestasis, mitochondrial impairment, bile-acid accumulation',
  population_relevance:
    'Human iPSC-derived hepatocyte organoids from three donor lines (male/female, CYP2C9*1/*3 polymorphism represented) to capture inter-individual variability.',
  limitations: [
    'Organoid system lacks sinusoidal blood flow and Kupffer-cell mediated immune activation.',
    'Long-term (>28 day) cultures show phenotypic drift; findings limited to acute and sub-chronic windows.',
    'Biliary canalicular efflux transport may be underrepresented in batch lots used.',
    'Oxidative metabolism comparable to primary hepatocytes only for CYP3A4/2C9; CYP1A2 activity low.',
  ],
  acceptance_criteria: [
    'Z\u2019 factor ≥0.5 for each cytotoxicity endpoint across three independent runs.',
    'Bile acid accumulation assay CV ≤25% intra-assay, ≤30% inter-assay.',
    'Reference hepatotoxicant panel (n=12 compounds): sensitivity ≥75%, specificity ≥70%.',
    'Human Cmax multiple coverage ≥30× achieved at highest test concentration.',
  ],
  regulatory_confidence_level: 'supportive',
  version: '1.2',
  created_at: '2026-01-15T09:00:00Z',
  updated_at: '2026-04-20T11:15:00Z',
};

// ─── NAM Study ────────────────────────────────────────────────────────────────

export const demoStudy: NAMStudy = {
  study_id: 'NAM-STUDY-001',
  project_id: 'proj-hep-001',
  context_of_use_id: 'COU-HEP-001',
  title: 'CX-4471 Hepatotoxicity Assessment in iPSC-Derived Liver Organoids (NAMO-aligned)',
  model_system: {
    namo_class: 'Organoid',
    species: 'Homo sapiens',
    cell_type: 'iPSC-derived hepatocyte-like cells',
    tissue_origin: 'Liver (hepatocellular)',
    culture_conditions:
      '3D self-assembling organoid; HepatiCult Organoid Growth Medium; 5% CO₂, 37 °C; Matrigel-embedded domes',
    vendor: 'STEMCELL Technologies / in-house differentiation',
    catalog_number: 'HepatiCult-OGM-hum',
    passage_number: 'P3–P8',
    maturity_indicators: [
      'Albumin secretion >200 ng/mL/day',
      'CYP3A4 induction ratio ≥5× by rifampicin',
      'HNF4α nuclear localisation >80% cells',
      'Tight junction formation (ZO-1 immunofluorescence)',
    ],
  },
  experimental_design: {
    study_type: 'Concentration-response, multi-endpoint',
    concentrations_uM: [0.1, 0.3, 1, 3, 10, 30, 100],
    vehicle: 'DMSO (0.1% final)',
    treatment_duration_hours: [24, 72],
    replicates: 'n=3 biological replicates × 3 technical replicates',
    reference_compounds: [
      { name: 'Acetaminophen', class: 'Hepatotoxicant', expected: 'positive' },
      { name: 'Fialuridine', class: 'Mitochondrial toxicant', expected: 'positive' },
      { name: 'Troglitazone', class: 'DILI reference', expected: 'positive' },
      { name: 'Metformin', class: 'Non-hepatotoxic control', expected: 'negative' },
    ],
  },
  assay_metadata: {
    primary_endpoints: [
      {
        name: 'ATP viability',
        method: 'CellTiter-Glo 3D',
        readout: 'luminescence',
        unit: '% vehicle control',
      },
      {
        name: 'LDH release',
        method: 'CytoTox-ONE',
        readout: 'fluorescence',
        unit: '% max lysis',
      },
      {
        name: 'Mitochondrial membrane potential',
        method: 'JC-1 dye',
        readout: 'ratiometric fluorescence',
        unit: 'J-aggregates:J-monomers ratio',
      },
      {
        name: 'Intracellular bile acid accumulation',
        method: 'LC-MS/MS (targeted)',
        readout: 'peak area ratio',
        unit: 'pmol per mg protein',
      },
      {
        name: 'ROS generation',
        method: 'CellROX Green',
        readout: 'fluorescence',
        unit: 'fold over vehicle',
      },
    ],
    instrument: 'EnVision multilabel plate reader; Agilent 6500 QTOF',
    software: 'TIBCO Spotfire 12.1, GraphPad Prism 10',
  },
  data_outputs: {
    tc50_atp_uM: { value: 18.4, ci95: [14.2, 23.8], unit: 'µM' },
    tc50_ldh_uM: { value: 22.1, ci95: [17.5, 28.9], unit: 'µM' },
    noael_uM: { value: 3.0, unit: 'µM' },
    human_cmax_uM: 0.6,
    safety_multiple_noael: 5.0,
    safety_multiple_tc50: 30.7,
    bile_acid_increase_fold: 3.2,
    mmp_decrease_pct_at_10uM: 41,
  },
  provenance: {
    study_director: 'Dr. A. Okonkwo',
    facility: 'Neuronautix In Vitro Pharmacology, Cambridge UK',
    study_initiation_date: '2026-02-01',
    study_completion_date: '2026-03-28',
    raw_data_location: 'LIMS-NNX/studies/NAM-STUDY-001',
    analysis_script: 'github.com/neuronautix/cx4471-hep-analysis/v1.2',
    sop_references: ['SOP-IVTOX-003 v2.1', 'SOP-ORGNOID-005 v1.4'],
    audit_trail: 'ELN entries NNX-2026-0201 through NNX-2026-0328',
  },
  created_at: '2026-04-01T10:00:00Z',
};

// ─── Evidence Matrix ───────────────────────────────────────────────────────────

export const demoEvidenceItems: EvidenceItem[] = [
  // Analytical Validity
  {
    evidence_id: 'EVID-001',
    study_id: 'NAM-STUDY-001',
    domain: 'analytical_validity',
    question: 'Is the assay endpoint (ATP viability) analytically validated with Z\u2019 ≥ 0.5?',
    evidence_type: 'Assay validation data',
    status: 'met',
    notes: "Z' = 0.72 (mean of 3 runs). Meets acceptance criterion of ≥0.5.",
    supporting_data: 'Table 2, Study Report NAM-STUDY-001',
  },
  {
    evidence_id: 'EVID-002',
    study_id: 'NAM-STUDY-001',
    domain: 'analytical_validity',
    question: 'Are all analytical readouts within dynamic range and free from interference at tested concentrations?',
    evidence_type: 'Interference check panel',
    status: 'met',
    notes: 'Fluorescence interference excluded by counter-screen; compound colour-quench not detected.',
    supporting_data: 'Appendix B, NAM-STUDY-001',
  },
  // Technical Reproducibility
  {
    evidence_id: 'EVID-003',
    study_id: 'NAM-STUDY-001',
    domain: 'technical_reproducibility',
    question: 'Is the coefficient of variation (CV) ≤25% intra-assay for all primary endpoints?',
    evidence_type: 'Replicate statistics',
    status: 'met',
    notes: 'CV range 8–22% across ATP, LDH, MMP endpoints. Bile acid LC-MS/MS CV 18% intra-assay.',
    supporting_data: 'Table 3',
  },
  {
    evidence_id: 'EVID-004',
    study_id: 'NAM-STUDY-001',
    domain: 'technical_reproducibility',
    question: 'Do results replicate across three independent biological replicates?',
    evidence_type: 'Inter-replicate analysis',
    status: 'met',
    notes: 'TC50 ATP values: 17.8, 18.1, 19.3 µM across runs. Geometric CV = 4.4%.',
    supporting_data: 'Figure 3A',
  },
  // Biological Relevance
  {
    evidence_id: 'EVID-005',
    study_id: 'NAM-STUDY-001',
    domain: 'biological_relevance',
    question: 'Does the model express key hepatic transporters (OATP1B1, BSEP, MRP2) relevant to DILI?',
    evidence_type: 'Transporter expression (RT-PCR + immunostaining)',
    status: 'partial',
    notes: 'OATP1B1 and MRP2 expressed. BSEP expression ~30% of primary hepatocyte level – reduced canalicular efflux capacity is a documented limitation.',
    supporting_data: 'Supplementary Figure S2',
  },
  {
    evidence_id: 'EVID-006',
    study_id: 'NAM-STUDY-001',
    domain: 'biological_relevance',
    question: 'Does the organoid system recapitulate key metabolic CYP450 activities relevant to CX-4471 metabolism?',
    evidence_type: 'CYP induction/activity profiling',
    status: 'partial',
    notes: 'CYP3A4 and CYP2C9 activity comparable to cryopreserved hepatocytes. CYP1A2 activity <20% – limits detection of reactive metabolite-driven toxicity via this pathway.',
    supporting_data: 'Table S1',
  },
  // Reference Compound Performance
  {
    evidence_id: 'EVID-007',
    study_id: 'NAM-STUDY-001',
    domain: 'reference_compound_performance',
    question: 'Does the model correctly classify the 12-compound reference hepatotoxicant panel?',
    evidence_type: 'Reference panel concordance',
    status: 'met',
    notes: 'Sensitivity 83% (10/12 positives detected), specificity 75% (3/4 negatives). Meets pre-specified acceptance criteria.',
    supporting_data: 'Table 4, Supplementary Table S3',
  },
  {
    evidence_id: 'EVID-008',
    study_id: 'NAM-STUDY-001',
    domain: 'reference_compound_performance',
    question: 'Are positive control responses within expected historical ranges in each run?',
    evidence_type: 'Positive control tracking',
    status: 'met',
    notes: 'Acetaminophen TC50 1.8–2.3 mM (historical 1.5–2.8 mM). Fialuridine MMP50 within ±1 SD historical range.',
    supporting_data: 'Control charts, Appendix C',
  },
  // Exposure Relevance
  {
    evidence_id: 'EVID-009',
    study_id: 'NAM-STUDY-001',
    domain: 'exposure_relevance',
    question: 'Does the highest tested concentration achieve ≥30× the projected human Cmax?',
    evidence_type: 'Exposure multiple analysis',
    status: 'met',
    notes: 'Highest concentration 100 µM; projected human Cmax 0.6 µM (PK simulation). Coverage = 167×. TC50 safety multiple = 30.7×.',
    supporting_data: 'Section 4.3, Exposure Assessment Memo EXP-CX4471-001',
  },
  {
    evidence_id: 'EVID-010',
    study_id: 'NAM-STUDY-001',
    domain: 'exposure_relevance',
    question: 'Is free (unbound) intracellular concentration considered in toxicity interpretation?',
    evidence_type: 'Protein binding / bioavailability correction',
    status: 'partial',
    notes: 'Plasma protein binding 94% (fu,plasma = 0.06). Intracellular partitioning estimated from log P; in vitro free fraction not directly measured in organoid matrix.',
    supporting_data: 'Memo EXP-CX4471-001, Section 3.2',
  },
  // Data Integrity
  {
    evidence_id: 'EVID-011',
    study_id: 'NAM-STUDY-001',
    domain: 'data_integrity',
    question: 'Are raw data retained in a compliant electronic laboratory notebook with audit trail?',
    evidence_type: 'ELN / LIMS documentation',
    status: 'met',
    notes: 'All raw instrument files and plate layouts archived in LIMS-NNX with 21 CFR Part 11-aligned audit trail.',
    supporting_data: 'Provenance section, NAM-STUDY-001',
  },
  {
    evidence_id: 'EVID-012',
    study_id: 'NAM-STUDY-001',
    domain: 'data_integrity',
    question: 'Is analysis pipeline version-controlled and reproducible?',
    evidence_type: 'Computational reproducibility',
    status: 'met',
    notes: 'Analysis scripts committed to Git (tagged v1.2); Docker image archived for computational reproducibility.',
    supporting_data: 'Provenance section, NAM-STUDY-001',
  },
  // Limitation Analysis
  {
    evidence_id: 'EVID-013',
    study_id: 'NAM-STUDY-001',
    domain: 'limitation_analysis',
    question: 'Are known model limitations clearly documented and their impact on interpretation assessed?',
    evidence_type: 'Limitation register',
    status: 'met',
    notes: 'Four limitations documented in COU-HEP-001. BSEP underexpression and CYP1A2 gap flagged as interpretive caveats in study report.',
    supporting_data: 'COU-HEP-001 v1.2; Study Report Section 7',
  },
  // Regulatory Alignment
  {
    evidence_id: 'EVID-014',
    study_id: 'NAM-STUDY-001',
    domain: 'regulatory_alignment',
    question: 'Is the study design aligned with FDA/EMA guidance on in vitro hepatotoxicity assessment?',
    evidence_type: 'Regulatory guidance mapping',
    status: 'met',
    notes: 'Aligned with ICH S7A principles, FDA 2023 DILI draft guidance, and EMA non-clinical guideline concepts. Multi-endpoint design reflects best practice.',
    supporting_data: 'Regulatory Alignment Table, Study Report Section 2',
  },
  {
    evidence_id: 'EVID-015',
    study_id: 'NAM-STUDY-001',
    domain: 'regulatory_alignment',
    question: 'Is NAMO-compliant metadata captured to allow reproducibility and regulatory traceability?',
    evidence_type: 'NAMO metadata completeness audit',
    status: 'met',
    notes: 'NAMO-required fields for Organoid class all populated. Provenance, model system ontology terms, and assay metadata conform to NAMO v1.3 schema.',
    supporting_data: 'NAMO Metadata Export, NAM-STUDY-001',
  },
];

// ─── Claim Nodes ──────────────────────────────────────────────────────────────

export const demoClaimNodes: ClaimNode[] = [
  {
    claim_id: 'CLAIM-001',
    project_id: 'proj-hep-001',
    claim_text:
      'CX-4471 does not cause hepatocellular cytotoxicity at pharmacologically relevant exposures (≤3 µM; 5× human Cmax).',
    claim_type: 'empirical',
    context_of_use_id: 'COU-HEP-001',
    confidence: 'supportive',
    supporting_evidence: ['EVID-001', 'EVID-003', 'EVID-004', 'EVID-009'],
    contradictory_evidence: [],
    limitations: ['BSEP underexpression may underestimate cholestatic potential at therapeutic exposures.'],
    ectd_target_sections: ['4.2.3.7.3', '2.6.2'],
    review_status: 'human_review_required',
  },
  {
    claim_id: 'CLAIM-002',
    project_id: 'proj-hep-001',
    claim_text:
      'At ≥10 µM (16.7× human Cmax), CX-4471 causes mitochondrial membrane potential disruption (41% decrease) and bile acid accumulation (3.2× increase).',
    claim_type: 'empirical',
    context_of_use_id: 'COU-HEP-001',
    confidence: 'supportive',
    supporting_evidence: ['EVID-001', 'EVID-002', 'EVID-009', 'EVID-010'],
    contradictory_evidence: [],
    limitations: ['In vitro free fraction not directly measured; intracellular exposure is estimated.'],
    ectd_target_sections: ['4.2.3.7.3', '4.2.3.2'],
    review_status: 'human_review_required',
    parent_claim_id: 'CLAIM-001',
  },
  {
    claim_id: 'CLAIM-003',
    project_id: 'proj-hep-001',
    claim_text:
      'The organoid model used has sufficient biological relevance for hepatotoxicity hazard identification for CX-4471, with the documented limitation of reduced BSEP expression.',
    claim_type: 'mechanistic',
    context_of_use_id: 'COU-HEP-001',
    confidence: 'supportive',
    supporting_evidence: ['EVID-005', 'EVID-006', 'EVID-007', 'EVID-008'],
    contradictory_evidence: ['EVID-005'],
    limitations: [
      'BSEP expression ~30% of primary hepatocyte level.',
      'CYP1A2 activity limited – reactive metabolite pathways via CYP1A2 not adequately covered.',
    ],
    ectd_target_sections: ['4.2.3.7.3'],
    review_status: 'human_review_required',
  },
  {
    claim_id: 'CLAIM-004',
    project_id: 'proj-hep-001',
    claim_text:
      'The hepatotoxicity evidence package supports identification of the liver as a target organ for enhanced clinical monitoring in Phase I, but does not replace GLP repeat-dose toxicology studies.',
    claim_type: 'predictive',
    context_of_use_id: 'COU-HEP-001',
    confidence: 'decision_informing',
    supporting_evidence: ['EVID-011', 'EVID-012', 'EVID-013', 'EVID-014', 'EVID-015'],
    contradictory_evidence: [],
    limitations: [
      'Evidence is limited to acute and sub-chronic in vitro exposures.',
      'Immunological mechanisms of DILI not captured.',
    ],
    ectd_target_sections: ['2.6.2', '2.6.6', '4.2.3.7.3'],
    review_status: 'human_review_required',
    parent_claim_id: 'CLAIM-001',
  },
  {
    claim_id: 'CLAIM-005',
    project_id: 'proj-hep-001',
    claim_text:
      'The NOAEL from the organoid system (3 µM; 5× human Cmax projected) is consistent with a conservative starting dose and supports the proposed Phase I dose-escalation design.',
    claim_type: 'comparative',
    context_of_use_id: 'COU-HEP-001',
    confidence: 'exploratory',
    supporting_evidence: ['EVID-009', 'EVID-010'],
    contradictory_evidence: [],
    limitations: [
      'In vitro NOAEL is not a NOAEL in the regulatory sense; direct translation to in vivo dosing requires bridging toxicokinetic data.',
      'Allometric scaling and hepatic first-pass not modelled.',
    ],
    ectd_target_sections: ['2.6.6', '4.2.3.2'],
    review_status: 'human_review_required',
    parent_claim_id: 'CLAIM-004',
  },
];

export const demoClaimEdges: ClaimEdge[] = [
  { from_claim_id: 'CLAIM-003', to_claim_id: 'CLAIM-001', relationship: 'supports' },
  { from_claim_id: 'CLAIM-001', to_claim_id: 'CLAIM-004', relationship: 'supports' },
  { from_claim_id: 'CLAIM-002', to_claim_id: 'CLAIM-004', relationship: 'supports' },
  { from_claim_id: 'CLAIM-004', to_claim_id: 'CLAIM-005', relationship: 'supports' },
  { from_claim_id: 'CLAIM-003', to_claim_id: 'CLAIM-002', relationship: 'qualifies' },
];

// ─── eCTD Mappings ────────────────────────────────────────────────────────────

export const demoECTDMappings: ECTDMapping[] = [
  {
    mapping_id: 'ECTD-MAP-001',
    study_id: 'NAM-STUDY-001',
    claim_id: 'CLAIM-001',
    evidence_type: 'In vitro cytotoxicity (organoid)',
    ectd_section: '4.2.3.7.3',
    ectd_title: 'Other in vitro studies',
    notes:
      'Full study report, NAMO metadata export, and validation evidence matrix to be included. Label as "non-GLP, exploratory/supportive" per FDA NDI guidance.',
    justification:
      'Organoid hepatotoxicity study constitutes an in vitro safety pharmacology / toxicology study; placement in 4.2.3.7.3 is appropriate for non-GLP in vitro studies supplementing the nonclinical overview.',
  },
  {
    mapping_id: 'ECTD-MAP-002',
    study_id: 'NAM-STUDY-001',
    claim_id: 'CLAIM-002',
    evidence_type: 'Mitochondrial liability & cholestasis endpoints',
    ectd_section: '4.2.3.2',
    ectd_title: 'Toxicokinetics',
    notes:
      'Exposure-response data and safety margin calculations (based on projected human Cmax) to be referenced in the TK section to provide context for the observed toxicity thresholds.',
    justification: 'Safety multiple calculations link to TK section for proper cross-referencing with in vivo TK data when available.',
  },
  {
    mapping_id: 'ECTD-MAP-003',
    claim_id: 'CLAIM-004',
    evidence_type: 'Weight-of-evidence summary',
    ectd_section: '2.6.6',
    ectd_title: 'Toxicology Written Summary',
    notes:
      'Summary of hepatotoxicity evidence weight-of-evidence to be incorporated into the Toxicology Written Summary (2.6.6) as a subsection on special toxicity studies / novel methodologies.',
    justification: 'The interpretive claim and confidence assessment belong in the integrated written summary rather than raw data sections.',
  },
  {
    mapping_id: 'ECTD-MAP-004',
    claim_id: 'CLAIM-004',
    evidence_type: 'NAM methodology overview',
    ectd_section: '2.6.2',
    ectd_title: 'Pharmacology Written Summary',
    notes:
      'Brief description of NAM approach, COU framing, and limitations to be included in the Pharmacology Written Summary as a note on novel methodology used to characterise hepatic safety pharmacology.',
    justification:
      'FDA encourages disclosure of novel nonclinical tools and methods in the written summaries to provide reviewers context.',
  },
  {
    mapping_id: 'ECTD-MAP-005',
    study_id: 'NAM-STUDY-001',
    evidence_type: 'Validation evidence matrix (CSV)',
    ectd_section: '4.2.3.7.3',
    ectd_title: 'Other in vitro studies – supplementary validation data',
    notes:
      'Validation evidence matrix (EVID-MATRIX-001) to be included as a supporting file alongside the study report in section 4.2.3.7.3.',
    justification:
      'Providing the structured validation evidence matrix enables reviewers to assess model fitness-for-purpose without requiring deep familiarity with NAMO ontology.',
  },
];

// ─── eCTD Module 4 Tree ───────────────────────────────────────────────────────

export const ectdModule4Tree = [
  {
    section: '4',
    title: 'Nonclinical Study Reports',
    children: [
      {
        section: '4.1',
        title: 'Table of Contents of Module 4',
        children: [],
      },
      {
        section: '4.2',
        title: 'Study Reports',
        children: [
          {
            section: '4.2.1',
            title: 'Pharmacology',
            children: [
              { section: '4.2.1.1', title: 'Primary Pharmacodynamics', children: [] },
              { section: '4.2.1.2', title: 'Secondary Pharmacodynamics', children: [] },
              { section: '4.2.1.3', title: 'Safety Pharmacology', children: [] },
              { section: '4.2.1.4', title: 'Pharmacodynamic Drug Interactions', children: [] },
            ],
          },
          {
            section: '4.2.2',
            title: 'Pharmacokinetics',
            children: [
              { section: '4.2.2.1', title: 'Analytical Methods and Validation Reports', children: [] },
              { section: '4.2.2.2', title: 'Absorption', children: [] },
              { section: '4.2.2.3', title: 'Distribution', children: [] },
              { section: '4.2.2.4', title: 'Metabolism', children: [] },
              { section: '4.2.2.5', title: 'Excretion', children: [] },
            ],
          },
          {
            section: '4.2.3',
            title: 'Toxicology',
            children: [
              { section: '4.2.3.1', title: 'Single-Dose Toxicity', children: [] },
              { section: '4.2.3.2', title: 'Repeat-Dose Toxicity', children: [] },
              { section: '4.2.3.3', title: 'Genotoxicity', children: [] },
              { section: '4.2.3.4', title: 'Carcinogenicity', children: [] },
              { section: '4.2.3.5', title: 'Reproductive and Developmental Toxicology', children: [] },
              { section: '4.2.3.6', title: 'Local Tolerance', children: [] },
              {
                section: '4.2.3.7',
                title: 'Other Toxicity Studies',
                children: [
                  {
                    section: '4.2.3.7.1',
                    title: 'Antigenicity',
                    children: [],
                  },
                  {
                    section: '4.2.3.7.2',
                    title: 'Immunotoxicity',
                    children: [],
                  },
                  {
                    section: '4.2.3.7.3',
                    title: 'Other In Vitro Studies',
                    children: [],
                    mapped: true,
                  },
                  {
                    section: '4.2.3.7.4',
                    title: 'Mechanistic Studies',
                    children: [],
                  },
                ],
              },
            ],
          },
        ],
      },
    ],
  },
];

// ─── Convenience: full demo data bundle ───────────────────────────────────────

export const demoData = {
  project: demoProject,
  cou: demoCOU,
  study: demoStudy,
  evidenceItems: demoEvidenceItems,
  claimNodes: demoClaimNodes,
  claimEdges: demoClaimEdges,
  ectdMappings: demoECTDMappings,
  ectdTree: ectdModule4Tree,
};

export default demoData;
