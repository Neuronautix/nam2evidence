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
