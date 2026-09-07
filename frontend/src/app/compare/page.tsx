'use client';

import { FormEvent, useCallback, useEffect, useState } from 'react';
import Link from 'next/link';
import { ArrowLeft, ExternalLink, FlaskConical, Search, Scale } from 'lucide-react';

const API = process.env.NEXT_PUBLIC_API_URL ?? 'http://localhost:8080';

type ComparisonRow = {
  project: { id: string; name: string; project_type: string; sponsor: string | null; source_name: string | null; source_url: string | null };
  method: { method_id: string | null; name: string | null; method_type: string; developer: string | null; maturity_status: string };
  source_project: { source_type: string; label: string; organisation: string | null; source_url: string | null } | null;
  context_of_use: { cou_id: string; regulatory_question: string; intended_use: string; decision_supported: string; biological_domain: string; endpoint_class: string; test_article_scope: string | null; regulatory_authority: string | null; support_level: string; limitations: string[] };
  evidence_profile: { study_count: number; evidence_item_count: number; covered_domains: number; total_domains: number; status_counts: { met: number; partial: number; not_met: number; not_applicable: number } };
  assessments: Array<{ assessment_type: string; assessor_organization: string; authority: string | null; status: string; conclusion: string | null; source_url: string | null }>;
};

type ComparisonResponse = {
  count: number;
  rows: ComparisonRow[];
  facets: { method_types: string[]; biological_domains: string[]; regulatory_authorities: string[]; project_types: string[] };
  interpretation_note: string;
};

function humanize(value: string): string {
  return value.replaceAll('_', ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}

export default function ComparePage() {
  const [data, setData] = useState<ComparisonResponse | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [filters, setFilters] = useState({ q: '', biological_domain: '', method_type: '' });

  const load = useCallback(async (nextFilters = filters) => {
    setLoading(true); setError(null);
    try {
      const params = new URLSearchParams();
      (Object.entries(nextFilters) as Array<[string, string]>).forEach(([key, value]) => { if (value.trim()) params.set(key, value.trim()); });
      const response = await fetch(`${API}/api/v1/compare/contexts?${params.toString()}`, { cache: 'no-store' });
      if (!response.ok) throw new Error(`Comparison API returned ${response.status}`);
      setData((await response.json()) as ComparisonResponse);
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Failed to load NAM comparison data');
    } finally { setLoading(false); }
  }, [filters]);

  useEffect(() => {
    void load({ q: '', biological_domain: '', method_type: '' });
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  function submit(e: FormEvent) { e.preventDefault(); void load(filters); }

  return (
    <div className="min-h-screen bg-slate-50">
      <header className="bg-white border-b border-slate-200 px-6 py-4">
        <div className="max-w-7xl mx-auto flex items-center justify-between gap-4">
          <div className="flex items-center gap-3">
            <div className="w-9 h-9 rounded-lg bg-blue-600 flex items-center justify-center"><Scale className="w-5 h-5 text-white" /></div>
            <div><h1 className="text-base font-semibold text-slate-900">Compare NAMs by Context of Use</h1><p className="text-xs text-slate-500">Cross-project evidence profiles, not a validity leaderboard</p></div>
          </div>
          <Link href="/" className="btn-secondary"><ArrowLeft className="w-4 h-4" />Projects</Link>
        </div>
      </header>

      <main className="max-w-7xl mx-auto px-6 py-8">
        <div className="card p-5 mb-6">
          <form onSubmit={submit} className="grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
            <div className="md:col-span-2">
              <label className="label">Search question, method, project, endpoint</label>
              <input className="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" value={filters.q} onChange={(e) => setFilters({ ...filters, q: e.target.value })} placeholder="e.g. DILI, skin sensitisation, liver-chip" />
            </div>
            <div>
              <label className="label">Biological domain</label>
              <input list="biological-domains" className="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" value={filters.biological_domain} onChange={(e) => setFilters({ ...filters, biological_domain: e.target.value })} placeholder="All domains" />
              <datalist id="biological-domains">{data?.facets.biological_domains.map((v) => <option key={v} value={v} />)}</datalist>
            </div>
            <div>
              <label className="label">Method type</label>
              <select className="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" value={filters.method_type} onChange={(e) => setFilters({ ...filters, method_type: e.target.value })}>
                <option value="">All method types</option>{data?.facets.method_types.map((v) => <option key={v} value={v}>{humanize(v)}</option>)}
              </select>
            </div>
            <div className="md:col-span-4 flex justify-end"><button type="submit" className="btn-primary"><Search className="w-4 h-4" />Compare</button></div>
          </form>
        </div>

        {data?.interpretation_note && <div className="mb-6 border border-amber-200 bg-amber-50 rounded-lg px-4 py-3 text-sm text-amber-900">{data.interpretation_note}</div>}
        {error && <div className="card p-4 border-red-200 text-sm text-red-700">{error}</div>}
        {loading && <div className="text-sm text-slate-500">Loading comparison data…</div>}

        {!loading && data && <>
          <div className="flex items-baseline justify-between mb-4"><h2 className="text-sm font-semibold text-slate-500 uppercase tracking-wide">Comparable CoU records</h2><span className="text-sm text-slate-500">{data.count} record{data.count === 1 ? '' : 's'}</span></div>
          <div className="overflow-x-auto card"><table className="min-w-[1180px] w-full text-sm">
            <thead className="bg-slate-100 text-slate-600 text-xs uppercase tracking-wide"><tr><th className="text-left px-4 py-3">Method / source</th><th className="text-left px-4 py-3">Context of Use</th><th className="text-left px-4 py-3">Scope</th><th className="text-left px-4 py-3">Evidence profile</th><th className="text-left px-4 py-3">External assessments</th></tr></thead>
            <tbody className="divide-y divide-slate-200">{data.rows.map((row) => {
              const methodName = row.method.name || humanize(row.method.method_type);
              return <tr key={`${row.project.id}-${row.context_of_use.cou_id}`} className="align-top bg-white">
                <td className="px-4 py-4 w-60"><div className="flex items-start gap-2"><FlaskConical className="w-4 h-4 text-blue-600 mt-0.5 flex-shrink-0" /><div><p className="font-semibold text-slate-900">{methodName}</p><p className="text-xs text-slate-500 mt-0.5">{humanize(row.method.method_type)}</p>{row.method.developer && <p className="text-xs text-slate-500">Developer: {row.method.developer}</p>}<p className="text-xs text-slate-400 mt-2">{row.project.name}</p>{row.source_project?.source_url && <a href={row.source_project.source_url} target="_blank" rel="noreferrer" className="text-xs text-blue-600 hover:underline inline-flex items-center gap-1 mt-1">Source <ExternalLink className="w-3 h-3" /></a>}</div></div></td>
                <td className="px-4 py-4 w-[360px]"><p className="font-medium text-slate-900">{row.context_of_use.regulatory_question}</p><p className="text-xs text-slate-500 mt-2"><span className="font-medium">Use:</span> {row.context_of_use.intended_use || '—'}</p><p className="text-xs text-slate-500 mt-1"><span className="font-medium">Decision:</span> {row.context_of_use.decision_supported || '—'}</p><div className="flex gap-2 mt-2 flex-wrap"><span className="badge bg-blue-50 text-blue-700">{row.context_of_use.cou_id}</span><span className="badge bg-slate-100 text-slate-700">{humanize(row.context_of_use.support_level)}</span></div></td>
                <td className="px-4 py-4 w-64 text-xs text-slate-600"><p><span className="font-medium">Domain:</span> {row.context_of_use.biological_domain || '—'}</p><p className="mt-1"><span className="font-medium">Endpoint:</span> {row.context_of_use.endpoint_class || '—'}</p><p className="mt-1"><span className="font-medium">Articles:</span> {row.context_of_use.test_article_scope || '—'}</p><p className="mt-1"><span className="font-medium">Authority:</span> {row.context_of_use.regulatory_authority || '—'}</p></td>
                <td className="px-4 py-4 w-56 text-xs text-slate-600"><p className="font-medium text-slate-900">{row.evidence_profile.covered_domains}/{row.evidence_profile.total_domains} domains represented</p><p className="mt-2">Studies: {row.evidence_profile.study_count}</p><p>Evidence items: {row.evidence_profile.evidence_item_count}</p><div className="grid grid-cols-2 gap-x-3 gap-y-1 mt-2"><span>Met: {row.evidence_profile.status_counts.met}</span><span>Partial: {row.evidence_profile.status_counts.partial}</span><span>Not met: {row.evidence_profile.status_counts.not_met}</span><span>N/A: {row.evidence_profile.status_counts.not_applicable}</span></div></td>
                <td className="px-4 py-4 w-72">{row.assessments.length === 0 ? <p className="text-xs text-slate-400">No assessment record</p> : row.assessments.map((assessment, index) => <div key={`${assessment.assessment_type}-${index}`} className="mb-3 last:mb-0 text-xs"><div className="flex items-center gap-2 flex-wrap"><span className="font-medium text-slate-900">{assessment.assessor_organization}</span><span className="badge bg-slate-100 text-slate-700">{humanize(assessment.status)}</span></div><p className="text-slate-500 mt-1">{humanize(assessment.assessment_type)}</p>{assessment.conclusion && <p className="text-slate-600 mt-1">{assessment.conclusion}</p>}{assessment.source_url && <a href={assessment.source_url} target="_blank" rel="noreferrer" className="text-blue-600 hover:underline inline-flex items-center gap-1 mt-1">Assessment source <ExternalLink className="w-3 h-3" /></a>}</div>)}</td>
              </tr>;
            })}</tbody>
          </table></div>
        </>}
      </main>
    </div>
  );
}
