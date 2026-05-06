// ─── Enumerations ──────────────────────────────────────────────────────────────

export type RegulatoryConfidenceLevel =
  | 'exploratory'
  | 'supportive'
  | 'decision_informing'
  | 'potentially_pivotal';

export type ReviewStatus =
  | 'draft'
  | 'pending'
  | 'validated'
  | 'reviewer_pending'
  | 'human_review_required'
  | 'approved'
  | 'rejected';

export type EvidenceDomain =
  | 'analytical_validity'
  | 'technical_reproducibility'
  | 'biological_relevance'
  | 'reference_compound_performance'
  | 'exposure_relevance'
  | 'data_integrity'
  | 'limitation_analysis'
  | 'regulatory_alignment';

export type EvidenceStatus = 'met' | 'partial' | 'not_met' | 'not_applicable';

export type NAMModelType =
  | 'Organoid'
  | 'OrganOnChip'
  | 'QSARModel'
  | 'CellBasedAssay'
  | 'ComputationalModel'
  | 'ExVivo'
  | 'Spheroid'
  | '3DPrintedTissue';

export type DrugDevelopmentStage =
  | 'discovery'
  | 'lead_optimisation'
  | 'pre_IND'
  | 'IND_enabling'
  | 'phase_I'
  | 'phase_II'
  | 'phase_III';

// ─── Core Entities ─────────────────────────────────────────────────────────────

export interface Project {
  id: string;
  name: string;
  description: string;
  drug_name: string;
  sponsor: string;
  created_at: string;
  updated_at: string;
  review_status: ReviewStatus;
}

export interface ContextOfUseCard {
  cou_id: string;
  project_id: string;
  nam_type: NAMModelType;
  regulatory_question: string;
  drug_development_stage: DrugDevelopmentStage;
  intended_use: string;
  decision_supported: string;
  biological_domain: string;
  endpoint_class: string;
  population_relevance: string;
  limitations: string[];
  acceptance_criteria: string[];
  regulatory_confidence_level: RegulatoryConfidenceLevel;
  version: string;
  created_at: string;
  updated_at: string;
}

export interface ModelSystem {
  namo_class: NAMModelType;
  species: string;
  cell_type: string;
  tissue_origin: string;
  culture_conditions: string;
  vendor: string;
  catalog_number?: string;
  passage_number?: string;
  maturity_indicators?: string[];
}

export interface NAMStudy {
  study_id: string;
  project_id: string;
  context_of_use_id: string;
  title: string;
  model_system: ModelSystem;
  experimental_design: Record<string, unknown>;
  assay_metadata: Record<string, unknown>;
  data_outputs: Record<string, unknown>;
  provenance: Record<string, unknown>;
  created_at: string;
}

export interface EvidenceItem {
  evidence_id: string;
  study_id: string;
  domain: EvidenceDomain;
  question: string;
  evidence_type: string;
  status: EvidenceStatus;
  notes: string;
  supporting_data?: string;
}

export interface ClaimNode {
  claim_id: string;
  project_id: string;
  claim_text: string;
  claim_type: 'mechanistic' | 'empirical' | 'comparative' | 'predictive';
  context_of_use_id: string;
  confidence: RegulatoryConfidenceLevel;
  supporting_evidence: string[];
  contradictory_evidence: string[];
  limitations: string[];
  ectd_target_sections: string[];
  review_status: ReviewStatus;
  parent_claim_id?: string;
}

export type ClaimEdgeRelationship =
  | 'supports'
  | 'contradicts'
  | 'refutes'
  | 'qualifies'
  | 'depends_on'
  | 'requires'
  | 'limited_by'
  | 'derived_from'
  | 'conforms_to'
  | 'maps_to_ectd_section';

export interface ClaimEdge {
  from_claim_id: string;
  to_claim_id: string;
  relationship: ClaimEdgeRelationship;
}

export interface ECTDSection {
  section: string;
  title: string;
  parent?: string;
  description?: string;
}

export type ECTDMappingConfidence = 'low' | 'medium' | 'high';

export interface ECTDMapping {
  mapping_id: string;
  study_id?: string;
  claim_id?: string;
  document_title?: string;
  evidence_type: string;
  ectd_section: string;
  ectd_title: string;
  notes: string;
  justification?: string;
  confidence?: ECTDMappingConfidence;
}

export interface ValidationMatrix {
  study_id: string;
  items: EvidenceItem[];
}

export interface ExportPackage {
  package_id: string;
  project_id: string;
  context_of_use: ContextOfUseCard;
  nam_study: NAMStudy;
  evidence_matrix: EvidenceItem[];
  claim_nodes: ClaimNode[];
  claim_edges: ClaimEdge[];
  ectd_mappings: ECTDMapping[];
  exported_at: string;
  version: string;
}
