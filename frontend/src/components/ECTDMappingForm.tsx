'use client';

import { ECTDMapping, ECTDMappingConfidence } from '@/lib/types';
import { useState, useEffect, FormEvent } from 'react';
import { X } from 'lucide-react';

// Valid eCTD Module 4 / 2 section anchor list from the brief
// (initial_prompt.md lines 237-246).
export const VALID_ECTD_SECTIONS: Array<{ section: string; title: string }> = [
  { section: '4.2.1.1', title: 'Primary pharmacodynamics' },
  { section: '4.2.1.2', title: 'Secondary pharmacodynamics' },
  { section: '4.2.1.3', title: 'Safety pharmacology' },
  { section: '4.2.2', title: 'Pharmacokinetics' },
  { section: '4.2.3.2', title: 'Repeat-dose toxicity' },
  { section: '4.2.3.3', title: 'Genotoxicity' },
  { section: '4.2.3.7.3', title: 'Mechanistic studies / Other in vitro studies' },
  { section: '4.2.3.7.5', title: 'Metabolite-related studies' },
  { section: '4.2.3.7.6', title: 'Impurity-related studies' },
  { section: '4.3', title: 'Literature references' },
  { section: '2.4', title: 'Nonclinical Overview' },
  { section: '2.6', title: 'Nonclinical Written and Tabulated Summaries' },
  { section: '2.6.2', title: 'Pharmacology Written Summary' },
  { section: '2.6.6', title: 'Toxicology Written Summary' },
];

interface ECTDMappingFormProps {
  initial?: ECTDMapping;
  claimIds: string[];
  studyIds: string[];
  onSubmit: (mapping: ECTDMapping) => void;
  onCancel: () => void;
}

export default function ECTDMappingForm({
  initial,
  claimIds,
  studyIds,
  onSubmit,
  onCancel,
}: ECTDMappingFormProps) {
  const isEdit = Boolean(initial);

  const [section, setSection] = useState(initial?.ectd_section ?? VALID_ECTD_SECTIONS[0].section);
  const [documentTitle, setDocumentTitle] = useState(initial?.document_title ?? '');
  const [evidenceType, setEvidenceType] = useState(initial?.evidence_type ?? '');
  const [claimId, setClaimId] = useState(initial?.claim_id ?? '');
  const [studyId, setStudyId] = useState(initial?.study_id ?? '');
  const [justification, setJustification] = useState(initial?.justification ?? '');
  const [notes, setNotes] = useState(initial?.notes ?? '');
  const [confidence, setConfidence] = useState<ECTDMappingConfidence>(
    initial?.confidence ?? 'medium'
  );
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    function onKey(e: KeyboardEvent) {
      if (e.key === 'Escape') onCancel();
    }
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [onCancel]);

  function handleSubmit(e: FormEvent) {
    e.preventDefault();
    if (!evidenceType.trim()) {
      setError('Evidence type is required.');
      return;
    }
    if (!justification.trim()) {
      setError('Justification is required.');
      return;
    }
    const sectionMeta = VALID_ECTD_SECTIONS.find((s) => s.section === section);
    const mapping: ECTDMapping = {
      mapping_id: initial?.mapping_id ?? `ECTD-MAP-${Date.now()}`,
      study_id: studyId || undefined,
      claim_id: claimId || undefined,
      document_title: documentTitle.trim() || undefined,
      evidence_type: evidenceType.trim(),
      ectd_section: section,
      ectd_title: sectionMeta?.title ?? section,
      notes: notes.trim(),
      justification: justification.trim(),
      confidence,
    };
    onSubmit(mapping);
  }

  return (
    <div
      className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4"
      role="dialog"
      aria-modal="true"
      onClick={(e) => {
        if (e.target === e.currentTarget) onCancel();
      }}
    >
      <form
        onSubmit={handleSubmit}
        className="bg-white rounded-xl shadow-lg w-full max-w-lg max-h-[90vh] overflow-y-auto"
      >
        <div className="flex items-center justify-between p-5 border-b border-slate-200">
          <h2 className="text-lg font-semibold text-slate-900">
            {isEdit ? 'Edit eCTD Mapping' : 'Add eCTD Mapping'}
          </h2>
          <button
            type="button"
            onClick={onCancel}
            className="text-slate-400 hover:text-slate-700"
            aria-label="Close"
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        <div className="p-5 space-y-4">
          <div>
            <label className="label" htmlFor="ectd-section">
              eCTD Section
            </label>
            <select
              id="ectd-section"
              className="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
              value={section}
              onChange={(e) => setSection(e.target.value)}
            >
              {VALID_ECTD_SECTIONS.map((s) => (
                <option key={s.section} value={s.section}>
                  {s.section} — {s.title}
                </option>
              ))}
            </select>
          </div>

          <div>
            <label className="label" htmlFor="ectd-doc-title">
              Document Title
            </label>
            <input
              id="ectd-doc-title"
              type="text"
              className="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
              value={documentTitle}
              onChange={(e) => setDocumentTitle(e.target.value)}
              placeholder="e.g. CX-4471 hepatotoxicity organoid study report"
            />
          </div>

          <div>
            <label className="label" htmlFor="ectd-evidence-type">
              Evidence Type
            </label>
            <input
              id="ectd-evidence-type"
              type="text"
              className="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
              value={evidenceType}
              onChange={(e) => setEvidenceType(e.target.value)}
              required
            />
          </div>

          <div className="grid grid-cols-2 gap-3">
            <div>
              <label className="label" htmlFor="ectd-claim">
                Claim Reference
              </label>
              <select
                id="ectd-claim"
                className="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                value={claimId}
                onChange={(e) => setClaimId(e.target.value)}
              >
                <option value="">— none —</option>
                {claimIds.map((cid) => (
                  <option key={cid} value={cid}>
                    {cid}
                  </option>
                ))}
              </select>
            </div>
            <div>
              <label className="label" htmlFor="ectd-study">
                Study Reference
              </label>
              <select
                id="ectd-study"
                className="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                value={studyId}
                onChange={(e) => setStudyId(e.target.value)}
              >
                <option value="">— none —</option>
                {studyIds.map((sid) => (
                  <option key={sid} value={sid}>
                    {sid}
                  </option>
                ))}
              </select>
            </div>
          </div>

          <div>
            <label className="label" htmlFor="ectd-justification">
              Justification
            </label>
            <textarea
              id="ectd-justification"
              rows={3}
              className="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 resize-y"
              value={justification}
              onChange={(e) => setJustification(e.target.value)}
              required
            />
          </div>

          <div>
            <label className="label" htmlFor="ectd-notes">
              Notes
            </label>
            <textarea
              id="ectd-notes"
              rows={2}
              className="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 resize-y"
              value={notes}
              onChange={(e) => setNotes(e.target.value)}
            />
          </div>

          <div>
            <label className="label" htmlFor="ectd-confidence">
              Confidence
            </label>
            <select
              id="ectd-confidence"
              className="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
              value={confidence}
              onChange={(e) => setConfidence(e.target.value as ECTDMappingConfidence)}
            >
              <option value="low">Low</option>
              <option value="medium">Medium</option>
              <option value="high">High</option>
            </select>
          </div>

          {error && (
            <div className="p-3 rounded-lg bg-red-50 border border-red-200 text-xs text-red-800">
              {error}
            </div>
          )}
        </div>

        <div className="flex items-center justify-end gap-2 p-5 border-t border-slate-200 bg-slate-50 rounded-b-xl">
          <button type="button" className="btn-secondary" onClick={onCancel}>
            Cancel
          </button>
          <button type="submit" className="btn-primary">
            {isEdit ? 'Save changes' : 'Add mapping'}
          </button>
        </div>
      </form>
    </div>
  );
}
