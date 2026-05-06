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

interface AppState {
  projects: Project[];
  cous: ContextOfUseCard[];
  studies: NAMStudy[];
  evidenceItems: EvidenceItem[];
  claimNodes: ClaimNode[];
  claimEdges: ClaimEdge[];
  ectdMappings: ECTDMapping[];
}

const STORAGE_KEY = 'namo_ind_mapper_state';

const defaultState: AppState = {
  projects: [demoData.project],
  cous: [demoData.cou],
  studies: [demoData.study],
  evidenceItems: demoData.evidenceItems,
  claimNodes: demoData.claimNodes,
  claimEdges: demoData.claimEdges,
  ectdMappings: demoData.ectdMappings,
};

interface StoreContextValue {
  state: AppState;
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
  importNAMOStudy: (projectId: string, parsed: Record<string, unknown>) => NAMStudy;
  updateStudy: (studyId: string, partial: Partial<NAMStudy>) => void;
  addECTDMapping: (mapping: ECTDMapping) => void;
  updateECTDMapping: (mappingId: string, partial: Partial<ECTDMapping>) => void;
  deleteECTDMapping: (mappingId: string) => void;
}

const StoreContext = createContext<StoreContextValue | null>(null);

function loadState(): AppState {
  if (typeof window === 'undefined') return defaultState;
  try {
    const raw = localStorage.getItem(STORAGE_KEY);
    if (!raw) return defaultState;
    return JSON.parse(raw) as AppState;
  } catch {
    return defaultState;
  }
}

function saveState(state: AppState): void {
  if (typeof window === 'undefined') return;
  try {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(state));
  } catch {
    // storage quota exceeded — ignore
  }
}

export function StoreProvider({ children }: { children: ReactNode }) {
  const [state, setState] = useState<AppState>(defaultState);
  const [hydrated, setHydrated] = useState(false);

  useEffect(() => {
    setState(loadState());
    setHydrated(true);
  }, []);

  useEffect(() => {
    if (hydrated) saveState(state);
  }, [state, hydrated]);

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
  }, []);

  const updateClaimStatus = useCallback((claimId: string, status: ClaimNode['review_status']) => {
    setState((prev) => ({
      ...prev,
      claimNodes: prev.claimNodes.map((c) =>
        c.claim_id === claimId ? { ...c, review_status: status } : c
      ),
    }));
  }, []);

  const updateEvidenceStatus = useCallback(
    (evidenceId: string, status: EvidenceItem['status'], notes?: string) => {
      setState((prev) => ({
        ...prev,
        evidenceItems: prev.evidenceItems.map((e) =>
          e.evidence_id === evidenceId
            ? { ...e, status, notes: notes !== undefined ? notes : e.notes }
            : e
        ),
      }));
    },
    []
  );

  const createProject = useCallback((project: Project) => {
    setState((prev) => ({ ...prev, projects: [...prev.projects, project] }));
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
