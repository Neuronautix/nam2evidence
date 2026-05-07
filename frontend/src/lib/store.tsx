'use client';

import { createContext, useContext, useState, useEffect, ReactNode, useCallback } from 'react';
import {
  Project,
  ContextOfUseCard,
  NAMStudy,
  EvidenceItem,
  ClaimNode,
  ClaimEdge,
  ECTDMapping,
} from './types';
import demoData from './demoData';
import {
  createProjectApi,
  DATA_MODE,
  fetchProjects,
  fetchWorkspace,
  updateClaimStatusApi,
  updateCOUApi,
  updateEvidenceApi,
} from './api';

interface AppState {
  projects: Project[];
  cous: ContextOfUseCard[];
  studies: NAMStudy[];
  evidenceItems: EvidenceItem[];
  claimNodes: ClaimNode[];
  claimEdges: ClaimEdge[];
  ectdMappings: ECTDMapping[];
}

const defaultState: AppState = {
  projects: [demoData.project],
  cous: [demoData.cou],
  studies: [demoData.study],
  evidenceItems: demoData.evidenceItems,
  claimNodes: demoData.claimNodes,
  claimEdges: demoData.claimEdges,
  ectdMappings: demoData.ectdMappings,
};

const emptyState: AppState = {
  projects: [],
  cous: [],
  studies: [],
  evidenceItems: [],
  claimNodes: [],
  claimEdges: [],
  ectdMappings: [],
};

interface StoreContextValue {
  state: AppState;
  dataMode: string;
  loading: boolean;
  error: string | null;
  getProject: (id: string) => Project | undefined;
  getCOU: (projectId: string) => ContextOfUseCard | undefined;
  getStudy: (projectId: string) => NAMStudy | undefined;
  getEvidenceItems: (studyId: string) => EvidenceItem[];
  getClaimNodes: (projectId: string) => ClaimNode[];
  getClaimEdges: (projectId: string) => ClaimEdge[];
  getECTDMappings: (projectId: string) => ECTDMapping[];
  updateCOU: (cou: ContextOfUseCard) => void;
  updateClaimStatus: (claimId: string, status: ClaimNode['review_status']) => void;
  updateEvidenceStatus: (evidenceId: string, status: EvidenceItem['status'], notes?: string) => void;
  createProject: (project: Project) => void;
  loadProjectData: (projectId: string) => Promise<void>;
  importNAMOStudy: (projectId: string, parsed: Record<string, unknown>) => NAMStudy;
  updateStudy: (studyId: string, partial: Partial<NAMStudy>) => void;
  addECTDMapping: (mapping: ECTDMapping) => void;
  updateECTDMapping: (mappingId: string, partial: Partial<ECTDMapping>) => void;
  deleteECTDMapping: (mappingId: string) => void;
}

const StoreContext = createContext<StoreContextValue | null>(null);

export function StoreProvider({ children }: { children: ReactNode }) {
  const [state, setState] = useState<AppState>(DATA_MODE === 'demo' ? defaultState : emptyState);
  const [loading, setLoading] = useState(DATA_MODE !== 'demo');
  const [error, setError] = useState<string | null>(null);

  const mergeWorkspace = useCallback((workspace: AppState) => {
    setState((prev) => {
      const mergedProjects = [
        ...prev.projects.filter((p) => p.id !== workspace.projects[0]?.id),
        ...workspace.projects,
      ];

      return {
        projects: mergedProjects,
        cous: [
          ...prev.cous.filter((c) => !workspace.projects.some((p) => p.id === c.project_id)),
          ...workspace.cous,
        ],
        studies: [
          ...prev.studies.filter((s) => !workspace.projects.some((p) => p.id === s.project_id)),
          ...workspace.studies,
        ],
        evidenceItems: [
          ...prev.evidenceItems.filter(
            (e) => !workspace.studies.some((s) => s.study_id === e.study_id)
          ),
          ...workspace.evidenceItems,
        ],
        claimNodes: [
          ...prev.claimNodes.filter((c) => !workspace.projects.some((p) => p.id === c.project_id)),
          ...workspace.claimNodes,
        ],
        claimEdges: [
          ...prev.claimEdges.filter(
            (e) =>
              !workspace.claimNodes.some((c) => c.claim_id === e.from_claim_id || c.claim_id === e.to_claim_id)
          ),
          ...workspace.claimEdges,
        ],
        ectdMappings: [
          ...prev.ectdMappings.filter(
            (m) =>
              !workspace.studies.some((s) => s.study_id === m.study_id) &&
              !workspace.claimNodes.some((c) => c.claim_id === m.claim_id)
          ),
          ...workspace.ectdMappings,
        ],
      };
    });
  }, []);

  const loadProjectData = useCallback(async (projectId: string) => {
    if (DATA_MODE === 'demo') return;

    try {
      const workspace = await fetchWorkspace(projectId);
      mergeWorkspace({
        projects: [workspace.project],
        cous: workspace.cous,
        studies: workspace.studies,
        evidenceItems: workspace.evidenceItems,
        claimNodes: workspace.claimNodes,
        claimEdges: workspace.claimEdges,
        ectdMappings: workspace.ectdMappings,
      });
      setError(null);
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Failed to load project workspace');
    }
  }, [mergeWorkspace]);

  useEffect(() => {
    if (DATA_MODE === 'demo') {
      setLoading(false);
      return;
    }

    let active = true;
    (async () => {
      try {
        const projects = await fetchProjects();
        if (!active) return;
        setState((prev) => ({ ...prev, projects }));
        await Promise.all(projects.map((p) => loadProjectData(p.id)));
      } catch (e) {
        if (!active) return;
        setError(e instanceof Error ? e.message : 'Failed to initialize API data');
      } finally {
        if (active) setLoading(false);
      }
    })();

    return () => {
      active = false;
    };
  }, [loadProjectData]);

  const getProject = useCallback(
    (id: string) => state.projects.find((p) => p.id === id),
    [state.projects]
  );

  const getCOU = useCallback(
    (projectId: string) => state.cous.find((c) => c.project_id === projectId),
    [state.cous]
  );

  const getStudy = useCallback(
    (projectId: string) => state.studies.find((s) => s.project_id === projectId),
    [state.studies]
  );

  const getEvidenceItems = useCallback(
    (studyId: string) => state.evidenceItems.filter((e) => e.study_id === studyId),
    [state.evidenceItems]
  );

  const getClaimNodes = useCallback(
    (projectId: string) => state.claimNodes.filter((c) => c.project_id === projectId),
    [state.claimNodes]
  );

  const getClaimEdges = useCallback(
    (projectId: string) => {
      const nodes = state.claimNodes.filter((c) => c.project_id === projectId).map((c) => c.claim_id);
      return state.claimEdges.filter(
        (e) => nodes.includes(e.from_claim_id) || nodes.includes(e.to_claim_id)
      );
    },
    [state.claimNodes, state.claimEdges]
  );

  const getECTDMappings = useCallback(
    (projectId: string) => {
      const study = state.studies.find((s) => s.project_id === projectId);
      const claims = state.claimNodes.filter((c) => c.project_id === projectId).map((c) => c.claim_id);
      return state.ectdMappings.filter(
        (m) => (study && m.study_id === study.study_id) || (m.claim_id && claims.includes(m.claim_id))
      );
    },
    [state.studies, state.claimNodes, state.ectdMappings]
  );

  const updateCOU = useCallback((cou: ContextOfUseCard) => {
    setState((prev) => ({
      ...prev,
      cous: prev.cous.map((c) => (c.cou_id === cou.cou_id ? cou : c)),
    }));

    if (DATA_MODE !== 'demo') {
      void updateCOUApi(cou.project_id, cou).catch((e) => {
        setError(e instanceof Error ? e.message : 'Failed to update COU');
      });
    }
  }, [loadProjectData]);

  const updateClaimStatus = useCallback((claimId: string, status: ClaimNode['review_status']) => {
    const claim = state.claimNodes.find((c) => c.claim_id === claimId);
    setState((prev) => ({
      ...prev,
      claimNodes: prev.claimNodes.map((c) =>
        c.claim_id === claimId ? { ...c, review_status: status } : c
      ),
    }));

    if (DATA_MODE !== 'demo' && claim) {
      void updateClaimStatusApi(claim.project_id, claimId, status).catch((e) => {
        setError(e instanceof Error ? e.message : 'Failed to update claim status');
      });
    }
  }, [state.claimNodes]);

  const updateEvidenceStatus = useCallback(
    (evidenceId: string, status: EvidenceItem['status'], notes?: string) => {
      const target = state.evidenceItems.find((e) => e.evidence_id === evidenceId);
      const targetStudy = target ? state.studies.find((s) => s.study_id === target.study_id) : undefined;

      setState((prev) => ({
        ...prev,
        evidenceItems: prev.evidenceItems.map((e) =>
          e.evidence_id === evidenceId
            ? { ...e, status, notes: notes !== undefined ? notes : e.notes }
            : e
        ),
      }));

        if (DATA_MODE !== 'demo' && target && targetStudy) {
          const updated: EvidenceItem = {
            ...target,
            status,
            notes: notes !== undefined ? notes : target.notes,
          };

          void updateEvidenceApi(targetStudy.project_id, updated).catch((e) => {
            setError(e instanceof Error ? e.message : 'Failed to update evidence');
          });
        }
    },
      [state.evidenceItems, state.studies]
  );

  const createProject = useCallback((project: Project) => {
      if (DATA_MODE === 'demo') {
        setState((prev) => ({ ...prev, projects: [...prev.projects, project] }));
        return;
      }

      void createProjectApi({
        name: project.name,
        description: project.description,
        drug_name: project.drug_name,
        sponsor: project.sponsor,
      })
        .then(async (created) => {
          setState((prev) => ({ ...prev, projects: [created, ...prev.projects] }));
          await loadProjectData(created.id);
        })
        .catch((e) => {
          setError(e instanceof Error ? e.message : 'Failed to create project');
        });
  }, []);

  const importNAMOStudy = useCallback(
    (projectId: string, parsed: Record<string, unknown>): NAMStudy => {
      const studyId =
        (typeof parsed.id === 'string' && parsed.id) ||
        (typeof parsed.study_id === 'string' && parsed.study_id) ||
        `NAM-STUDY-${Date.now()}`;
      const studyName =
        (typeof parsed.name === 'string' && parsed.name) ||
        (typeof parsed.study_name === 'string' && parsed.study_name) ||
        (typeof parsed.title === 'string' && parsed.title) ||
        'Imported NAM Study';

      const modelSystem = (parsed.model_system as NAMStudy['model_system']) ?? {
        namo_class: 'Organoid',
        species: '',
        cell_type: '',
        tissue_origin: '',
        culture_conditions: '',
        vendor: '',
      };

      const isRecord = (v: unknown): v is Record<string, unknown> =>
        typeof v === 'object' && v !== null && !Array.isArray(v);

      const study: NAMStudy = {
        study_id: studyId as string,
        project_id: projectId,
        context_of_use_id:
          (typeof parsed.context_of_use_id === 'string' && parsed.context_of_use_id) || '',
        title: studyName as string,
        model_system: modelSystem,
        experimental_design: isRecord(parsed.experimental_design)
          ? parsed.experimental_design
          : {},
        assay_metadata: isRecord(parsed.assay_metadata) ? parsed.assay_metadata : {},
        data_outputs: isRecord(parsed.data_outputs) ? parsed.data_outputs : {},
        provenance: isRecord(parsed.provenance) ? parsed.provenance : {},
        created_at: new Date().toISOString(),
      };

      setState((prev) => {
        const existingIdx = prev.studies.findIndex((s) => s.project_id === projectId);
        const studies =
          existingIdx >= 0
            ? prev.studies.map((s, i) => (i === existingIdx ? study : s))
            : [...prev.studies, study];
        return { ...prev, studies };
      });

      return study;
    },
    []
  );

  const updateStudy = useCallback((studyId: string, partial: Partial<NAMStudy>) => {
    setState((prev) => ({
      ...prev,
      studies: prev.studies.map((s) => (s.study_id === studyId ? { ...s, ...partial } : s)),
    }));
  }, []);

  const addECTDMapping = useCallback((mapping: ECTDMapping) => {
    setState((prev) => ({ ...prev, ectdMappings: [...prev.ectdMappings, mapping] }));
  }, []);

  const updateECTDMapping = useCallback(
    (mappingId: string, partial: Partial<ECTDMapping>) => {
      setState((prev) => ({
        ...prev,
        ectdMappings: prev.ectdMappings.map((m) =>
          m.mapping_id === mappingId ? { ...m, ...partial } : m
        ),
      }));
    },
    []
  );

  const deleteECTDMapping = useCallback((mappingId: string) => {
    setState((prev) => ({
      ...prev,
      ectdMappings: prev.ectdMappings.filter((m) => m.mapping_id !== mappingId),
    }));
  }, []);

  return (
    <StoreContext.Provider
      value={{
        state,
          dataMode: DATA_MODE,
          loading,
          error,
        getProject,
        getCOU,
        getStudy,
        getEvidenceItems,
        getClaimNodes,
        getClaimEdges,
        getECTDMappings,
        updateCOU,
        updateClaimStatus,
        updateEvidenceStatus,
        createProject,
        loadProjectData,
        importNAMOStudy,
        updateStudy,
        addECTDMapping,
        updateECTDMapping,
        deleteECTDMapping,
      }}
    >
      {children}
    </StoreContext.Provider>
  );
}

export function useStore(): StoreContextValue {
  const ctx = useContext(StoreContext);
  if (!ctx) throw new Error('useStore must be used within StoreProvider');
  return ctx;
}
