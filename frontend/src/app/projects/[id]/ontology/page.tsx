'use client';

import { useCallback, useEffect, useState } from 'react';
import { useParams } from 'next/navigation';

const API = process.env.NEXT_PUBLIC_API_URL ?? 'http://localhost:8080';

interface SuggestedTerm {
  curie: string;
  label: string;
}

interface OntologyMapping {
  id: string;
  project_id: string;
  source_entity_type: string;
  source_field: string;
  source_value: string;
  mapping_status: string;
  mapping_confidence: number | null;
  mandatory: boolean;
  suggested_term: SuggestedTerm | null;
  reviewer_note: string | null;
  reviewed_by: string | null;
}

interface MappingSummary {
  total: number;
  approved: number;
  suggested: number;
  unmapped: number;
  rejected: number;
  mandatory_unresolved: number;
}

interface MappingsResponse {
  mappings: OntologyMapping[];
  summary: MappingSummary;
}

interface OntologyTerm {
  id: string;
  label: string;
  ontology_prefix: string;
  curie: string;
  iri: string;
  definition: string | null;
  synonyms: string[];
  source: string | null;
  term_version: string | null;
}

function statusBadgeClass(status: string): string {
  switch (status.toLowerCase()) {
    case 'approved':
      return 'bg-green-100 text-green-700';
    case 'suggested':
      return 'bg-blue-100 text-blue-700';
    case 'rejected':
      return 'bg-rose-100 text-rose-700';
    case 'unmapped':
      return 'bg-amber-100 text-amber-700';
    default:
      return 'bg-slate-100 text-slate-600';
  }
}

export default function OntologyMappingPage() {
  const { id } = useParams<{ id: string }>();

  const [data, setData] = useState<MappingsResponse | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [actingId, setActingId] = useState<string | null>(null);

  const [query, setQuery] = useState('');
  const [terms, setTerms] = useState<OntologyTerm[]>([]);
  const [termsLoading, setTermsLoading] = useState(false);
  const [termsError, setTermsError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await fetch(`${API}/api/v1/projects/${id}/ontology-mappings`, {
        cache: 'no-store',
      });
      if (!res.ok) throw new Error(`API ${res.status}`);
      setData((await res.json()) as MappingsResponse);
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Failed to load mappings');
    } finally {
      setLoading(false);
    }
  }, [id]);

  useEffect(() => {
    void load();
  }, [load]);

  const approve = async (mid: string, termCurie?: string) => {
    setActingId(mid);
    try {
      await fetch(`${API}/api/v1/ontology/mappings/${mid}/approve`, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(termCurie ? { term_curie: termCurie } : {}),
      });
      await load();
    } catch {
      /* surfaced by reload */
    } finally {
      setActingId(null);
    }
  };

  const reject = async (mid: string) => {
    setActingId(mid);
    try {
      await fetch(`${API}/api/v1/ontology/mappings/${mid}/reject`, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({}),
      });
      await load();
    } catch {
      /* surfaced by reload */
    } finally {
      setActingId(null);
    }
  };

  const searchTerms = async () => {
    setTermsLoading(true);
    setTermsError(null);
    try {
      const res = await fetch(
        `${API}/api/v1/ontology/terms?q=${encodeURIComponent(query)}`,
        { cache: 'no-store' }
      );
      if (!res.ok) throw new Error(`API ${res.status}`);
      setTerms((await res.json()) as OntologyTerm[]);
    } catch (e) {
      setTermsError(e instanceof Error ? e.message : 'Term search failed');
    } finally {
      setTermsLoading(false);
    }
  };

  return (
    <div className="p-8 max-w-6xl">
      <div className="mb-6">
        <h1 className="text-2xl font-bold text-slate-900">Ontology Mapping</h1>
        <p className="text-sm text-slate-500 mt-1">
          Review and approve mappings of source values to standardized ontology terms.
        </p>
        <p className="text-xs text-amber-700 mt-2">
          POC standardization — requires qualified human review. Not an official submission standard.
        </p>
      </div>

      {loading ? (
        <p className="text-sm text-slate-500">Loading mappings…</p>
      ) : error ? (
        <p className="text-sm text-rose-700">{error}</p>
      ) : !data ? (
        <p className="text-sm text-slate-500">No data.</p>
      ) : (
        <>
          {data.summary.mandatory_unresolved > 0 ? (
            <div className="p-3 mb-4 rounded-lg border border-rose-200 bg-rose-50 text-rose-700 text-sm font-medium">
              AI-ready status is blocked while mandatory terms are unmapped
              ({data.summary.mandatory_unresolved} remaining).
            </div>
          ) : null}

          {/* Summary counts */}
          <div className="grid grid-cols-6 gap-3 mb-6">
            <SummaryCard label="Total" value={data.summary.total} />
            <SummaryCard label="Approved" value={data.summary.approved} tone="green" />
            <SummaryCard label="Suggested" value={data.summary.suggested} tone="blue" />
            <SummaryCard label="Unmapped" value={data.summary.unmapped} tone="amber" />
            <SummaryCard label="Rejected" value={data.summary.rejected} tone="slate" />
            <SummaryCard
              label="Mandatory unresolved"
              value={data.summary.mandatory_unresolved}
              tone={data.summary.mandatory_unresolved > 0 ? 'rose' : 'green'}
            />
          </div>

          {/* Mappings table */}
          <div className="rounded-xl border border-slate-200 bg-white p-5 mb-6 overflow-x-auto">
            <table className="w-full text-xs border border-slate-200">
              <thead className="bg-slate-50">
                <tr>
                  <th className="px-2 py-1.5 text-left font-medium text-slate-600">Source value</th>
                  <th className="px-2 py-1.5 text-left font-medium text-slate-600">Entity type</th>
                  <th className="px-2 py-1.5 text-left font-medium text-slate-600">Suggested term</th>
                  <th className="px-2 py-1.5 text-left font-medium text-slate-600">Status</th>
                  <th className="px-2 py-1.5 text-left font-medium text-slate-600">Mandatory</th>
                  <th className="px-2 py-1.5 text-left font-medium text-slate-600">Actions</th>
                </tr>
              </thead>
              <tbody>
                {data.mappings.map((m) => (
                  <tr key={m.id} className="odd:bg-white even:bg-slate-50">
                    <td className="px-2 py-1.5 text-slate-700">{m.source_value}</td>
                    <td className="px-2 py-1.5 text-slate-600">{m.source_entity_type}</td>
                    <td className="px-2 py-1.5 text-slate-700">
                      {m.suggested_term ? (
                        <span>
                          {m.suggested_term.label}{' '}
                          <span className="font-mono text-slate-400">({m.suggested_term.curie})</span>
                        </span>
                      ) : (
                        <span className="text-slate-400">—</span>
                      )}
                    </td>
                    <td className="px-2 py-1.5">
                      <span className={`badge text-xs ${statusBadgeClass(m.mapping_status)}`}>
                        {m.mapping_status}
                      </span>
                    </td>
                    <td className="px-2 py-1.5">
                      {m.mandatory ? (
                        <span className="badge bg-rose-100 text-rose-700 text-xs">required</span>
                      ) : (
                        <span className="text-slate-400">optional</span>
                      )}
                    </td>
                    <td className="px-2 py-1.5">
                      <div className="flex gap-1.5">
                        <button
                          className="px-2 py-1 rounded bg-green-600 text-white text-xs disabled:opacity-50"
                          onClick={() => void approve(m.id, m.suggested_term?.curie)}
                          disabled={actingId === m.id || m.mapping_status === 'approved'}
                        >
                          Approve
                        </button>
                        <button
                          className="px-2 py-1 rounded bg-rose-600 text-white text-xs disabled:opacity-50"
                          onClick={() => void reject(m.id)}
                          disabled={actingId === m.id || m.mapping_status === 'rejected'}
                        >
                          Reject
                        </button>
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
            {data.mappings.length === 0 ? (
              <p className="text-sm text-slate-500 mt-3">No mappings found.</p>
            ) : null}
          </div>
        </>
      )}

      {/* Browse terms */}
      <div className="rounded-xl border border-slate-200 bg-white p-5">
        <h2 className="text-sm font-semibold text-slate-800 mb-3">Browse ontology terms</h2>
        <div className="flex gap-2 mb-4">
          <input
            className="flex-1 rounded-lg border border-slate-300 p-2 text-sm"
            placeholder="Search terms…"
            value={query}
            onChange={(e) => setQuery(e.target.value)}
            onKeyDown={(e) => {
              if (e.key === 'Enter') void searchTerms();
            }}
          />
          <button
            className="btn-primary text-xs py-1.5"
            onClick={() => void searchTerms()}
            disabled={termsLoading}
          >
            {termsLoading ? 'Searching…' : 'Search'}
          </button>
        </div>
        {termsError ? (
          <p className="text-sm text-rose-700">{termsError}</p>
        ) : terms.length === 0 ? (
          <p className="text-sm text-slate-500">No terms to show.</p>
        ) : (
          <div className="space-y-2">
            {terms.map((t) => (
              <div key={t.id} className="border border-slate-200 rounded-lg p-3">
                <div className="flex items-center gap-2">
                  <span className="text-sm font-medium text-slate-800">{t.label}</span>
                  <span className="font-mono text-xs text-slate-400">{t.curie}</span>
                  <span className="badge bg-slate-100 text-slate-600 text-xs">{t.ontology_prefix}</span>
                </div>
                {t.definition ? (
                  <p className="text-xs text-slate-500 mt-1">{t.definition}</p>
                ) : null}
              </div>
            ))}
          </div>
        )}
      </div>
    </div>
  );
}

const toneClass: Record<string, string> = {
  green: 'text-green-700',
  blue: 'text-blue-700',
  amber: 'text-amber-700',
  rose: 'text-rose-700',
  slate: 'text-slate-700',
};

function SummaryCard({
  label,
  value,
  tone = 'slate',
}: {
  label: string;
  value: number;
  tone?: 'green' | 'blue' | 'amber' | 'rose' | 'slate';
}) {
  return (
    <div className="rounded-lg border border-slate-200 bg-white p-3 text-center">
      <div className={`text-xl font-bold ${toneClass[tone]}`}>{value}</div>
      <div className="text-xs text-slate-500 mt-0.5">{label}</div>
    </div>
  );
}
