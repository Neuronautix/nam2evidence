'use client';

import { EvidenceItem, EvidenceDomain } from '@/lib/types';
import StatusBadge from './StatusBadge';
import { useState } from 'react';

interface ValidationMatrixProps {
  items: EvidenceItem[];
  onStatusChange?: (id: string, status: EvidenceItem['status'], notes?: string) => void;
}

const DOMAIN_LABELS: Record<EvidenceDomain, string> = {
  analytical_validity: 'Analytical Validity',
  technical_reproducibility: 'Technical Reproducibility',
  biological_relevance: 'Biological Relevance',
  reference_compound_performance: 'Reference Compound Performance',
  exposure_relevance: 'Exposure Relevance',
  data_integrity: 'Data Integrity',
  limitation_analysis: 'Limitation Analysis',
  regulatory_alignment: 'Regulatory Alignment',
};

const DOMAIN_COLORS: Record<EvidenceDomain, string> = {
  analytical_validity: 'bg-blue-50 text-blue-700',
  technical_reproducibility: 'bg-teal-50 text-teal-700',
  biological_relevance: 'bg-violet-50 text-violet-700',
  reference_compound_performance: 'bg-pink-50 text-pink-700',
  exposure_relevance: 'bg-orange-50 text-orange-700',
  data_integrity: 'bg-slate-100 text-slate-700',
  limitation_analysis: 'bg-amber-50 text-amber-700',
  regulatory_alignment: 'bg-green-50 text-green-700',
};

const DOMAIN_ORDER: EvidenceDomain[] = [
  'analytical_validity',
  'technical_reproducibility',
  'biological_relevance',
  'reference_compound_performance',
  'exposure_relevance',
  'data_integrity',
  'limitation_analysis',
  'regulatory_alignment',
];

function groupBy(items: EvidenceItem[]): Record<string, EvidenceItem[]> {
  const result: Record<string, EvidenceItem[]> = {};
  for (const item of items) {
    if (!result[item.domain]) result[item.domain] = [];
    result[item.domain].push(item);
  }
  return result;
}

function summarise(items: EvidenceItem[]) {
  const met = items.filter((i) => i.status === 'met').length;
  const partial = items.filter((i) => i.status === 'partial').length;
  const not_met = items.filter((i) => i.status === 'not_met').length;
  return { met, partial, not_met, total: items.length };
}

export default function ValidationMatrix({ items, onStatusChange }: ValidationMatrixProps) {
  const grouped = groupBy(items);
  const [editingId, setEditingId] = useState<string | null>(null);
  const [editNotes, setEditNotes] = useState('');
  const summary = summarise(items);

  return (
    <div>
      {/* Summary bar */}
      <div className="grid grid-cols-4 gap-3 mb-6">
        {[
          { label: 'Total Criteria', value: summary.total, color: 'text-slate-700' },
          { label: 'Met', value: summary.met, color: 'text-green-700' },
          { label: 'Partial', value: summary.partial, color: 'text-amber-700' },
          { label: 'Not Met', value: summary.not_met, color: 'text-red-700' },
        ].map((s) => (
          <div key={s.label} className="card p-4 text-center">
            <p className={`text-2xl font-bold ${s.color}`}>{s.value}</p>
            <p className="text-xs text-slate-500 mt-1">{s.label}</p>
          </div>
        ))}
      </div>

      {/* Matrix */}
      <div className="space-y-6">
        {DOMAIN_ORDER.filter((d) => grouped[d]?.length).map((domain) => {
          const domainItems = grouped[domain];
          return (
            <div key={domain}>
              <div className="flex items-center gap-2 mb-2">
                <span className={`badge text-xs font-medium ${DOMAIN_COLORS[domain]}`}>
                  {DOMAIN_LABELS[domain]}
                </span>
                <span className="text-xs text-slate-400">
                  {domainItems.filter((i) => i.status === 'met').length}/{domainItems.length} met
                </span>
              </div>

              <div className="card overflow-hidden">
                <table className="w-full text-sm">
                  <thead>
                    <tr className="bg-slate-50 border-b border-slate-200">
                      <th className="text-left px-4 py-2.5 text-xs font-medium text-slate-500 w-10">ID</th>
                      <th className="text-left px-4 py-2.5 text-xs font-medium text-slate-500">Question</th>
                      <th className="text-left px-4 py-2.5 text-xs font-medium text-slate-500 w-36">Evidence Type</th>
                      <th className="text-left px-4 py-2.5 text-xs font-medium text-slate-500 w-28">Status</th>
                      <th className="text-left px-4 py-2.5 text-xs font-medium text-slate-500">Notes</th>
                      {onStatusChange && (
                        <th className="px-4 py-2.5 text-xs font-medium text-slate-500 w-20">Edit</th>
                      )}
                    </tr>
                  </thead>
                  <tbody>
                    {domainItems.map((item, idx) => (
                      <>
                        <tr
                          key={item.evidence_id}
                          className={idx % 2 === 0 ? 'bg-white' : 'bg-slate-50/50'}
                        >
                          <td className="px-4 py-3 text-xs font-mono text-slate-400">{item.evidence_id}</td>
                          <td className="px-4 py-3 text-xs text-slate-700 leading-relaxed">{item.question}</td>
                          <td className="px-4 py-3 text-xs text-slate-500">{item.evidence_type}</td>
                          <td className="px-4 py-3">
                            {onStatusChange ? (
                              <select
                                value={item.status}
                                onChange={(e) =>
                                  onStatusChange(item.evidence_id, e.target.value as EvidenceItem['status'])
                                }
                                className="text-xs border border-slate-200 rounded px-1 py-0.5 focus:outline-none focus:ring-1 focus:ring-blue-500"
                              >
                                <option value="met">Met</option>
                                <option value="partial">Partial</option>
                                <option value="not_met">Not Met</option>
                                <option value="not_applicable">N/A</option>
                              </select>
                            ) : (
                              <StatusBadge status={item.status} size="sm" />
                            )}
                          </td>
                          <td className="px-4 py-3 text-xs text-slate-500 leading-relaxed">{item.notes}</td>
                          {onStatusChange && (
                            <td className="px-4 py-3 text-center">
                              <button
                                onClick={() => {
                                  setEditingId(editingId === item.evidence_id ? null : item.evidence_id);
                                  setEditNotes(item.notes);
                                }}
                                className="text-xs text-blue-600 hover:text-blue-800"
                              >
                                {editingId === item.evidence_id ? 'Close' : 'Edit'}
                              </button>
                            </td>
                          )}
                        </tr>
                        {editingId === item.evidence_id && onStatusChange && (
                          <tr key={`${item.evidence_id}-edit`}>
                            <td colSpan={6} className="px-4 py-3 bg-blue-50 border-b border-blue-100">
                              <div className="flex gap-2 items-end">
                                <div className="flex-1">
                                  <label className="label">Notes</label>
                                  <textarea
                                    rows={2}
                                    className="w-full border border-slate-300 rounded px-2 py-1 text-xs resize-none focus:outline-none focus:ring-1 focus:ring-blue-500"
                                    value={editNotes}
                                    onChange={(e) => setEditNotes(e.target.value)}
                                  />
                                </div>
                                <button
                                  className="btn-primary text-xs py-1"
                                  onClick={() => {
                                    onStatusChange(item.evidence_id, item.status, editNotes);
                                    setEditingId(null);
                                  }}
                                >
                                  Save
                                </button>
                              </div>
                            </td>
                          </tr>
                        )}
                      </>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
          );
        })}
      </div>
    </div>
  );
}
