import {
  ClaimEdge,
  ClaimNode,
  ContextOfUseCard,
  DrugDevelopmentStage,
  ECTDMapping,
  EvidenceItem,
  NAMStudy,
  NAMModelType,
  Project,
  RegulatoryConfidenceLevel,
} from './types';

const API_BASE = process.env.NEXT_PUBLIC_API_URL ?? 'http://localhost:8080';

export const DATA_MODE = (process.env.NEXT_PUBLIC_DATA_MODE ?? 'api').toLowerCase();

export type ExportFormat = 'json' | 'csv' | 'md' | 'txt';

export class ApiDownloadError extends Error {
  readonly status: number;
  readonly pendingIds: string[];

  constructor(message: string, status: number, pendingIds: string[] = []) {
    super(message);
    this.name = 'ApiDownloadError';
    this.status = status;
    this.pendingIds = pendingIds;
  }
}

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

type ApiEntity = Record<string, unknown>;

function asString(value: unknown, fallback = ''): string {
  return typeof value === 'string' ? value : fallback;
}

function asStringArray(value: unknown): string[] {
  return Array.isArray(value) ? value.filter((item): item is string => typeof item === 'string') : [];
}

function asRecord(value: unknown): ApiEntity | undefined {
  return typeof value === 'object' && value !== null ? (value as ApiEntity) : undefined;
}

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

function parseFilename(contentDisposition: string | null): string | null {
  if (!contentDisposition) return null;

  const utf8Match = contentDisposition.match(/filename\*=UTF-8''([^;]+)/i);
  if (utf8Match?.[1]) {
    return decodeURIComponent(utf8Match[1]);
  }

  const asciiMatch = contentDisposition.match(/filename="?([^";]+)"?/i);
  return asciiMatch?.[1] ?? null;
}

async function requestDownload(path: string, init?: RequestInit): Promise<{ blob: Blob; filename: string }> {
  const headers = new Headers(init?.headers ?? {});
  headers.set('Accept', '*/*');

  const response = await fetch(`${API_BASE}${path}`, {
    ...init,
    headers,
    cache: 'no-store',
  });

  if (!response.ok) {
    let message = `API ${response.status}`;
    let pendingIds: string[] = [];

    try {
      const data = (await response.json()) as { error?: string; pending_ids?: string[] };
      message = data.error ?? message;
      pendingIds = Array.isArray(data.pending_ids) ? data.pending_ids : [];
    } catch {
      const text = await response.text();
      if (text) message = text;
    }

    throw new ApiDownloadError(message, response.status, pendingIds);
  }

  const filename = parseFilename(response.headers.get('Content-Disposition')) ?? 'export.bin';
  return {
    blob: await response.blob(),
    filename,
  };
}

export function saveBlob(blob: Blob, filename: string): void {
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = filename;
  a.click();
  URL.revokeObjectURL(url);
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

function toCOU(raw: ApiEntity): ContextOfUseCard {
  const project = asRecord(raw.project);

  return {
    cou_id: asString(raw.couId),
    project_id: asString(project?.id),
    nam_type: asString(raw.namType) as NAMModelType,
    regulatory_question: asString(raw.regulatoryQuestion),
    drug_development_stage: asString(raw.drugDevelopmentStage) as DrugDevelopmentStage,
    intended_use: asString(raw.intendedUse),
    decision_supported: asString(raw.decisionSupported),
    biological_domain: asString(raw.biologicalDomain),
    endpoint_class: asString(raw.endpointClass),
    population_relevance: asString(raw.populationRelevance),
    limitations: asStringArray(raw.limitations),
    acceptance_criteria: asStringArray(raw.acceptanceCriteria),
    regulatory_confidence_level: asString(raw.regulatoryConfidenceLevel) as RegulatoryConfidenceLevel,
    version: asString(raw.version, '1.0'),
    created_at: asString(raw.createdAt, new Date().toISOString()),
    updated_at: asString(raw.updatedAt, new Date().toISOString()),
  };
}

function toStudy(raw: ApiEntity): NAMStudy {
  const project = asRecord(raw.project);
  const contextOfUse = asRecord(raw.contextOfUse);

  return {
    study_id: asString(raw.studyId),
    project_id: asString(project?.id),
    context_of_use_id: asString(contextOfUse?.couId),
    title: asString(raw.title),
    model_system: ((asRecord(raw.modelSystem) ?? {}) as unknown) as NAMStudy['model_system'],
    experimental_design: asRecord(raw.experimentalDesign) ?? {},
    assay_metadata: asRecord(raw.assayMetadata) ?? {},
    data_outputs: asRecord(raw.dataOutputs) ?? {},
    provenance: asRecord(raw.provenance) ?? {},
    created_at: asString(raw.createdAt, new Date().toISOString()),
  };
}

function toEvidence(raw: ApiEntity): EvidenceItem {
  const study = asRecord(raw.study);

  return {
    evidence_id: asString(raw.evidenceId),
    study_id: asString(study?.studyId),
    domain: asString(raw.domain) as EvidenceItem['domain'],
    question: asString(raw.question),
    evidence_type: asString(raw.evidenceType),
    status: asString(raw.status) as EvidenceItem['status'],
    notes: asString(raw.notes),
    supporting_data: typeof raw.supportingData === 'string' ? raw.supportingData : undefined,
  };
}

function toClaim(raw: ApiEntity): ClaimNode {
  const project = asRecord(raw.project);
  const contextOfUse = asRecord(raw.contextOfUse);
  const parentClaim = asRecord(raw.parentClaim);

  return {
    claim_id: asString(raw.claimId),
    project_id: asString(project?.id),
    claim_text: asString(raw.claimText),
    claim_type: asString(raw.claimType) as ClaimNode['claim_type'],
    context_of_use_id: asString(contextOfUse?.couId),
    confidence: asString(raw.confidence) as ClaimNode['confidence'],
    supporting_evidence: asStringArray(raw.supportingEvidence),
    contradictory_evidence: asStringArray(raw.contradictoryEvidence),
    limitations: asStringArray(raw.limitations),
    ectd_target_sections: asStringArray(raw.ectdTargetSections),
    review_status: asString(raw.reviewStatus) as ClaimNode['review_status'],
    parent_claim_id: typeof parentClaim?.claimId === 'string' ? parentClaim.claimId : undefined,
  };
}

function toClaimEdge(raw: ApiEntity): ClaimEdge {
  const fromClaim = asRecord(raw.fromClaim);
  const toClaim = asRecord(raw.toClaim);

  return {
    from_claim_id: asString(fromClaim?.claimId),
    to_claim_id: asString(toClaim?.claimId),
    relationship: asString(raw.relationship) as ClaimEdge['relationship'],
  };
}

function toMapping(raw: ApiEntity): ECTDMapping {
  const study = asRecord(raw.study);
  const claim = asRecord(raw.claim);

  return {
    mapping_id: asString(raw.mappingId),
    study_id: typeof study?.studyId === 'string' ? study.studyId : undefined,
    claim_id: typeof claim?.claimId === 'string' ? claim.claimId : undefined,
    evidence_type: asString(raw.evidenceType),
    ectd_section: asString(raw.ectdSection),
    ectd_title: asString(raw.ectdTitle),
    notes: asString(raw.notes),
    justification: typeof raw.justification === 'string' ? raw.justification : undefined,
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
  const data = await request<ApiEntity>(`/api/v1/projects/${projectId}/cou/${cou.cou_id}`, {
    method: 'PUT',
    body: JSON.stringify(cou),
  });

  return toCOU(data);
}

export async function updateEvidenceApi(projectId: string, item: EvidenceItem): Promise<EvidenceItem> {
  const data = await request<ApiEntity>(`/api/v1/projects/${projectId}/evidence/${item.evidence_id}`, {
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
  const data = await request<ApiEntity>(`/api/v1/projects/${projectId}/claims/${claimId}/status`, {
    method: 'PUT',
    body: JSON.stringify({ status }),
  });

  return toClaim(data);
}

export async function generateAndDownloadExport(
  projectId: string,
  format: ExportFormat
): Promise<{ blob: Blob; filename: string }> {
  return requestDownload(`/api/projects/${projectId}/export/download?format=${format}`, {
    method: 'POST',
  });
}
