import {
  ClaimEdge,
  ClaimNode,
  ContextOfUseCard,
  ECTDMapping,
  EvidenceItem,
  NAMStudy,
  Project,
} from './types';

const API_BASE = process.env.NEXT_PUBLIC_API_URL ?? 'http://localhost:8080';

export const DATA_MODE = (process.env.NEXT_PUBLIC_DATA_MODE ?? 'api').toLowerCase();

type ApiProject = {
  id: string;
  name: string;
  description?: string | null;
  drugName: string;
  sponsor?: string | null;
  reviewStatus: Project['review_status'];
  createdAt?: string;
  updatedAt?: string;
};

type ApiWorkspace = {
  project: Record<string, unknown>;
  context_of_use_cards: Array<Record<string, unknown>>;
  nam_studies: Array<Record<string, unknown>>;
  evidence_items: Array<Record<string, unknown>>;
  claim_nodes: Array<Record<string, unknown>>;
  claim_edges: Array<Record<string, unknown>>;
  ectd_mappings: Array<Record<string, unknown>>;
};

async function request<T>(path: string, init?: RequestInit): Promise<T> {
  const response = await fetch(`${API_BASE}${path}`, {
    ...init,
    headers: {
      'Content-Type': 'application/json',
      ...(init?.headers ?? {}),
    },
    cache: 'no-store',
  });

  if (!response.ok) {
    const text = await response.text();
    throw new Error(`API ${response.status}: ${text}`);
  }

  return (await response.json()) as T;
}

function toProject(p: ApiProject): Project {
  return {
    id: p.id,
    name: p.name,
    description: p.description ?? '',
    drug_name: p.drugName,
    sponsor: p.sponsor ?? '',
    created_at: p.createdAt ?? new Date().toISOString(),
    updated_at: p.updatedAt ?? new Date().toISOString(),
    review_status: p.reviewStatus,
  };
}

function toCOU(raw: Record<string, any>): ContextOfUseCard {
  return {
    cou_id: raw.couId,
    project_id: raw.project?.id ?? '',
    nam_type: raw.namType,
    regulatory_question: raw.regulatoryQuestion,
    drug_development_stage: raw.drugDevelopmentStage,
    intended_use: raw.intendedUse,
    decision_supported: raw.decisionSupported,
    biological_domain: raw.biologicalDomain,
    endpoint_class: raw.endpointClass,
    population_relevance: raw.populationRelevance ?? '',
    limitations: Array.isArray(raw.limitations) ? raw.limitations : [],
    acceptance_criteria: Array.isArray(raw.acceptanceCriteria) ? raw.acceptanceCriteria : [],
    regulatory_confidence_level: raw.regulatoryConfidenceLevel,
    version: raw.version ?? '1.0',
    created_at: raw.createdAt ?? new Date().toISOString(),
    updated_at: raw.updatedAt ?? new Date().toISOString(),
  };
}

function toStudy(raw: Record<string, any>): NAMStudy {
  return {
    study_id: raw.studyId,
    project_id: raw.project?.id ?? '',
    context_of_use_id: raw.contextOfUse?.couId ?? '',
    title: raw.title ?? '',
    model_system: raw.modelSystem ?? {},
    experimental_design: raw.experimentalDesign ?? {},
    assay_metadata: raw.assayMetadata ?? {},
    data_outputs: raw.dataOutputs ?? {},
    provenance: raw.provenance ?? {},
    created_at: raw.createdAt ?? new Date().toISOString(),
  };
}

function toEvidence(raw: Record<string, any>): EvidenceItem {
  return {
    evidence_id: raw.evidenceId,
    study_id: raw.study?.studyId ?? '',
    domain: raw.domain,
    question: raw.question,
    evidence_type: raw.evidenceType,
    status: raw.status,
    notes: raw.notes ?? '',
    supporting_data: raw.supportingData ?? undefined,
  };
}

function toClaim(raw: Record<string, any>): ClaimNode {
  return {
    claim_id: raw.claimId,
    project_id: raw.project?.id ?? '',
    claim_text: raw.claimText,
    claim_type: raw.claimType,
    context_of_use_id: raw.contextOfUse?.couId ?? '',
    confidence: raw.confidence,
    supporting_evidence: Array.isArray(raw.supportingEvidence) ? raw.supportingEvidence : [],
    contradictory_evidence: Array.isArray(raw.contradictoryEvidence) ? raw.contradictoryEvidence : [],
    limitations: Array.isArray(raw.limitations) ? raw.limitations : [],
    ectd_target_sections: Array.isArray(raw.ectdTargetSections) ? raw.ectdTargetSections : [],
    review_status: raw.reviewStatus,
    parent_claim_id: raw.parentClaim?.claimId ?? undefined,
  };
}

function toClaimEdge(raw: Record<string, any>): ClaimEdge {
  return {
    from_claim_id: raw.fromClaim?.claimId ?? '',
    to_claim_id: raw.toClaim?.claimId ?? '',
    relationship: raw.relationship,
  };
}

function toMapping(raw: Record<string, any>): ECTDMapping {
  return {
    mapping_id: raw.mappingId,
    study_id: raw.study?.studyId ?? undefined,
    claim_id: raw.claim?.claimId ?? undefined,
    evidence_type: raw.evidenceType,
    ectd_section: raw.ectdSection,
    ectd_title: raw.ectdTitle,
    notes: raw.notes ?? '',
    justification: raw.justification ?? undefined,
  };
}

export async function fetchProjects(): Promise<Project[]> {
  const data = await request<ApiProject[]>('/api/v1/projects');
  return data.map(toProject);
}

export async function createProjectApi(input: {
  name: string;
  description?: string;
  drug_name: string;
  sponsor?: string;
}): Promise<Project> {
  const data = await request<ApiProject>('/api/v1/projects', {
    method: 'POST',
    body: JSON.stringify(input),
  });
  return toProject(data);
}

export async function fetchWorkspace(projectId: string): Promise<{
  project: Project;
  cous: ContextOfUseCard[];
  studies: NAMStudy[];
  evidenceItems: EvidenceItem[];
  claimNodes: ClaimNode[];
  claimEdges: ClaimEdge[];
  ectdMappings: ECTDMapping[];
}> {
  const data = await request<ApiWorkspace>(`/api/v1/projects/${projectId}/workspace`);

  return {
    project: toProject(data.project as ApiProject),
    cous: (data.context_of_use_cards ?? []).map(toCOU),
    studies: (data.nam_studies ?? []).map(toStudy),
    evidenceItems: (data.evidence_items ?? []).map(toEvidence),
    claimNodes: (data.claim_nodes ?? []).map(toClaim),
    claimEdges: (data.claim_edges ?? []).map(toClaimEdge),
    ectdMappings: (data.ectd_mappings ?? []).map(toMapping),
  };
}

export async function updateCOUApi(projectId: string, cou: ContextOfUseCard): Promise<ContextOfUseCard> {
  const data = await request<Record<string, any>>(`/api/v1/projects/${projectId}/cou/${cou.cou_id}`, {
    method: 'PUT',
    body: JSON.stringify(cou),
  });

  return toCOU(data);
}

export async function updateEvidenceApi(projectId: string, item: EvidenceItem): Promise<EvidenceItem> {
  const data = await request<Record<string, any>>(`/api/v1/projects/${projectId}/evidence/${item.evidence_id}`, {
    method: 'PUT',
    body: JSON.stringify({ status: item.status, notes: item.notes }),
  });

  return toEvidence(data);
}

export async function updateClaimStatusApi(
  projectId: string,
  claimId: string,
  status: ClaimNode['review_status']
): Promise<ClaimNode> {
  const data = await request<Record<string, any>>(`/api/v1/projects/${projectId}/claims/${claimId}/status`, {
    method: 'PUT',
    body: JSON.stringify({ status }),
  });

  return toClaim(data);
}
