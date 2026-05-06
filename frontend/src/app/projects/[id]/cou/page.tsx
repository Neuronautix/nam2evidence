'use client';

import { useParams } from 'next/navigation';
import { useStore } from '@/lib/store';
import { useState } from 'react';
import ConfidenceBadge from '@/components/ConfidenceBadge';
import { ContextOfUseCard } from '@/lib/types';
import { Save, Edit2 } from 'lucide-react';

export default function COUPage() {
  const { id } = useParams<{ id: string }>();
  const { getCOU, updateCOU } = useStore();
  const cou = getCOU(id);
  const [editing, setEditing] = useState(false);
  const [draft, setDraft] = useState<ContextOfUseCard | null>(null);

  if (!cou) {
    return <div className="p-8 text-slate-500">No Context of Use card found for this project.</div>;
  }

  const active = editing && draft ? draft : cou;

  function startEdit() {
    setDraft({ ...cou! });
    setEditing(true);
  }

  function save() {
    if (draft) {
      updateCOU({ ...draft, updated_at: new Date().toISOString() });
    }
    setEditing(false);
    setDraft(null);
  }

  function cancel() {
    setEditing(false);
    setDraft(null);
  }

  return (
    <div className="p-8 max-w-4xl">
      <div className="flex items-center justify-between mb-6">
        <div>
          <h1 className="text-2xl font-bold text-slate-900">Context of Use Card</h1>
          <p className="text-sm text-slate-500 mt-1">
            {cou.cou_id} · v{cou.version} · Updated {new Date(cou.updated_at).toLocaleDateString()}
          </p>
        </div>
        <div className="flex gap-2">
          {editing ? (
            <>
              <button className="btn-secondary" onClick={cancel}>Cancel</button>
              <button className="btn-primary" onClick={save}>
                <Save className="w-4 h-4" />Save
              </button>
            </>
          ) : (
            <button className="btn-secondary" onClick={startEdit}>
              <Edit2 className="w-4 h-4" />Edit
            </button>
          )}
        </div>
      </div>

      <div className="space-y-5">
        {/* Summary row */}
        <div className="card p-5 grid grid-cols-3 gap-6">
          <div>
            <p className="label">NAM Type</p>
            <p className="value font-medium">{active.nam_type}</p>
          </div>
          <div>
            <p className="label">Development Stage</p>
            <p className="value">{active.drug_development_stage.replace(/_/g, ' ')}</p>
          </div>
          <div>
            <p className="label">Regulatory Confidence</p>
            <ConfidenceBadge level={active.regulatory_confidence_level} />
          </div>
        </div>

        {/* Regulatory Question */}
        <div className="card p-5">
          <p className="label">Regulatory Question</p>
          {editing && draft ? (
            <textarea
              rows={3}
              className="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none"
              value={draft.regulatory_question}
              onChange={(e) => setDraft({ ...draft, regulatory_question: e.target.value })}
            />
          ) : (
            <p className="text-sm text-slate-800 leading-relaxed">{active.regulatory_question}</p>
          )}
        </div>

        {/* Intended Use / Decision */}
        <div className="card p-5 grid grid-cols-2 gap-6">
          <div>
            <p className="label">Intended Use</p>
            {editing && draft ? (
              <textarea
                rows={3}
                className="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none"
                value={draft.intended_use}
                onChange={(e) => setDraft({ ...draft, intended_use: e.target.value })}
              />
            ) : (
              <p className="text-sm text-slate-800 leading-relaxed">{active.intended_use}</p>
            )}
          </div>
          <div>
            <p className="label">Decision Supported</p>
            {editing && draft ? (
              <textarea
                rows={3}
                className="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none"
                value={draft.decision_supported}
                onChange={(e) => setDraft({ ...draft, decision_supported: e.target.value })}
              />
            ) : (
              <p className="text-sm text-slate-800 leading-relaxed">{active.decision_supported}</p>
            )}
          </div>
        </div>

        {/* Domain & Population */}
        <div className="card p-5 grid grid-cols-2 gap-6">
          <div>
            <p className="label">Biological Domain / Endpoint Class</p>
            <p className="value">{active.biological_domain}</p>
            <p className="text-xs text-slate-500 mt-1">{active.endpoint_class}</p>
          </div>
          <div>
            <p className="label">Population Relevance</p>
            <p className="text-sm text-slate-800 leading-relaxed">{active.population_relevance}</p>
          </div>
        </div>

        {/* Limitations */}
        <div className="card p-5">
          <p className="label">Documented Limitations</p>
          <ul className="space-y-2 mt-1">
            {active.limitations.map((lim, i) => (
              <li key={i} className="flex items-start gap-2 text-sm text-slate-700">
                <span className="text-amber-500 mt-0.5">⚠</span>
                <span>{lim}</span>
              </li>
            ))}
          </ul>
        </div>

        {/* Acceptance Criteria */}
        <div className="card p-5">
          <p className="label">Acceptance Criteria</p>
          <ul className="space-y-2 mt-1">
            {active.acceptance_criteria.map((ac, i) => (
              <li key={i} className="flex items-start gap-2 text-sm text-slate-700">
                <span className="text-green-500 mt-0.5">✓</span>
                <span>{ac}</span>
              </li>
            ))}
          </ul>
        </div>
      </div>
    </div>
  );
}
