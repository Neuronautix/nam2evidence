'use client';

import { useParams } from 'next/navigation';
import { useStore } from '@/lib/store';
import ECTDTree from '@/components/ECTDTree';
import ECTDMappingForm from '@/components/ECTDMappingForm';
import { useState } from 'react';
import { Plus } from 'lucide-react';
import { ECTDMapping } from '@/lib/types';

export default function ECTDPage() {
  const { id } = useParams<{ id: string }>();
  const {
    getECTDMappings,
    getClaimNodes,
    getStudy,
    addECTDMapping,
    updateECTDMapping,
    deleteECTDMapping,
  } = useStore();
  const mappings = getECTDMappings(id);
  const claims = getClaimNodes(id);
  const study = getStudy(id);

  const claimIds = claims.map((c) => c.claim_id);
  const studyIds = study ? [study.study_id] : [];

  const [formOpen, setFormOpen] = useState(false);
  const [editing, setEditing] = useState<ECTDMapping | null>(null);
  const [confirmDelete, setConfirmDelete] = useState<string | null>(null);

  function handleSubmit(mapping: ECTDMapping) {
    if (editing) {
      updateECTDMapping(editing.mapping_id, mapping);
    } else {
      addECTDMapping(mapping);
    }
    setFormOpen(false);
    setEditing(null);
  }

  function handleEdit(mapping: ECTDMapping) {
    setEditing(mapping);
    setFormOpen(true);
  }

  function handleDelete(mappingId: string) {
    setConfirmDelete(mappingId);
  }

  function confirmDeleteAction() {
    if (confirmDelete) {
      deleteECTDMapping(confirmDelete);
      setConfirmDelete(null);
    }
  }

  return (
    <div className="p-8 max-w-5xl">
      <div className="mb-6 flex items-start justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-slate-900">eCTD Module 4 Mapper</h1>
          <p className="text-sm text-slate-500 mt-1">
            Maps NAM evidence documents and claim summaries to the appropriate sections of an
            IND/eCTD Module 4 nonclinical study report hierarchy. Highlighted sections contain mapped
            evidence.
          </p>
        </div>
        <button
          type="button"
          className="btn-primary flex-shrink-0"
          onClick={() => {
            setEditing(null);
            setFormOpen(true);
          }}
        >
          <Plus className="w-4 h-4" />
          Add mapping
        </button>
      </div>

      <ECTDTree mappings={mappings} onEdit={handleEdit} onDelete={handleDelete} />

      {formOpen && (
        <ECTDMappingForm
          initial={editing ?? undefined}
          claimIds={claimIds}
          studyIds={studyIds}
          onSubmit={handleSubmit}
          onCancel={() => {
            setFormOpen(false);
            setEditing(null);
          }}
        />
      )}

      {confirmDelete && (
        <div
          className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4"
          role="dialog"
          aria-modal="true"
          onClick={(e) => {
            if (e.target === e.currentTarget) setConfirmDelete(null);
          }}
        >
          <div className="bg-white rounded-xl shadow-lg w-full max-w-sm p-5">
            <h3 className="text-base font-semibold text-slate-900 mb-2">Delete mapping?</h3>
            <p className="text-sm text-slate-600 mb-4">
              This will remove mapping{' '}
              <span className="font-mono text-slate-800">{confirmDelete}</span>. This action cannot
              be undone.
            </p>
            <div className="flex items-center justify-end gap-2">
              <button
                type="button"
                className="btn-secondary"
                onClick={() => setConfirmDelete(null)}
              >
                Cancel
              </button>
              <button
                type="button"
                className="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-red-600 text-white text-sm font-medium hover:bg-red-700 transition-colors"
                onClick={confirmDeleteAction}
              >
                Delete
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
