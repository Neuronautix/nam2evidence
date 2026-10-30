'use client';

import { FormEvent, useCallback, useEffect, useMemo, useState } from 'react';
import { useParams } from 'next/navigation';
import { BookOpen, CheckCircle2, ExternalLink, Link2, ShieldAlert, XCircle } from 'lucide-react';

const API = process.env.NEXT_PUBLIC_API_URL ?? 'http://localhost:8080';

type Relationship = 'supports' | 'partially_supports' | 'contradicts' | 'contextualizes' | 'not_comparable';
type TargetType = 'requirement_assessment' | 'claim' | 'evidence_item';

interface StatementLink {
  id: string;
  external_statement_id: string;
  target_type: TargetType;
  target: { id: string | null; key: string | null; label: string | null; status: string | null };
  relationship: Relationship;
  link_origin: string;
  review_status: 'suggested' | 'approved' | 'rejected';
  reviewed_by: string | null;
  reviewed_at: string | null;
  reviewer_note: string | null;
  comparability_note: string | null;
  provenance: Record<string, unknown>;
  counts_as_evidence: boolean;
}

interface Statement {
  id: string;
  source_system: string;
  statement_uri: string;
  record_uri: string | null;
  original_work_doi: string | null;
  source_version: string;
  statement_text: string;
  statement_kind: string;
  authors: string[];
  dataset_links: Array<{ uri: string; label?: string; licence?: string; checksum?: string }>;
  code_links: Array<{ uri: string; label?: string }>;
  study_context: Record<string, string>;
  uncertainty: Record<string, string>;
  licence: string | null;
  access_scope: 'public' | 'restricted' | 'unknown';
  payload_sha256: string;
  captured_at: string;
  captured_by: string;
  capture_status: string;
  verified_by: string | null;
  eligible_for_support: boolean;
  exportable: boolean;
  raw_payload: Record<string, unknown>;
  links: StatementLink[];
}

interface Summary {
  total: number;
  public: number;
  restricted: number;
  unknown: number;
  captured_verified: number;
  links_total: number;
  links_suggested: number;
  links_approved: number;
  links_rejected: number;
  links_not_comparable: number;
}

interface ListResponse {
  count: number;
  summary: Summary;
  statements: Statement[];
  interpretation_note: string;
}

interface Targets {
  requirement_assessments: Array<{ id: string; requirement_key: string; title: string; status: string }>;
  claims: Array<{ id: string; claim_id: string; claim_text: string; confidence: string; review_status: string }>;
  evidence_items: Array<{ id: string; evidence_id: string; domain: string; question: string; status: string }>;
}

const RELATIONSHIPS: Relationship[] = ['supports', 'partially_supports', 'contradicts', 'contextualizes', 'not_comparable'];

function humanize(v: string): string {
  return v.replaceAll('_', ' ');
}

function badge(kind: string): string {
  switch (kind) {
    case 'approved':
    case 'public':
    case 'captured_verified':
      return 'badge bg-green-100 text-green-700';
    case 'suggested':
    case 'captured_unverified':
      return 'badge bg-blue-100 text-blue-700';
    case 'rejected':
    case 'restricted':
    case 'withdrawn':
      return 'badge bg-rose-100 text-rose-700';
    case 'unknown':
      return 'badge bg-amber-100 text-amber-700';
    default:
      return 'badge bg-slate-100 text-slate-600';
  }
}

function relationshipBadge(rel: Relationship): string {
  switch (rel) {
    case 'supports':
      return 'badge bg-emerald-100 text-emerald-800';
    case 'partially_supports':
      return 'badge bg-lime-100 text-lime-800';
    case 'contradicts':
      return 'badge bg-orange-100 text-orange-800';
    case 'not_comparable':
      return 'badge bg-slate-200 text-slate-700';
    default:
      return 'badge bg-sky-100 text-sky-800';
  }
}

export default function ExternalEvidencePage() {
  const { id } = useParams<{ id: string }>();
  const [data, setData] = useState<ListResponse | null>(null);
  const [targets, setTargets] = useState<Targets | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [actionError, setActionError] = useState<string | null>(null);
  const [reviewer, setReviewer] = useState('');
  const [busy, setBusy] = useState<string | null>(null);
  const [openRaw, setOpenRaw] = useState<Record<string, boolean>>({});
  const [linkForm, setLinkForm] = useState<{ statementId: string; targetType: TargetType; targetId: string; relationship: Relationship; note: string } | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const [listRes, targetRes] = await Promise.all([
        fetch(`${API}/api/v1/projects/${id}/external-statements`, { cache: 'no-store' }),
        fetch(`${API}/api/v1/projects/${id}/external-statement-targets`, { cache: 'no-store' }),
      ]);
      if (!listRes.ok) throw new Error(`API ${listRes.status}`);
      setData((await listRes.json()) as ListResponse);
      if (targetRes.ok) setTargets((await targetRes.json()) as Targets);
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Failed to load external statements');
    } finally {
      setLoading(false);
    }
  }, [id]);

  useEffect(() => {
    void load();
  }, [load]);

  const requireReviewer = (): boolean => {
    if (reviewer.trim() === '') {
      setActionError('Enter your reviewer identity first. Every decision on an external statement is attributed to a named person.');
      return false;
    }
    return true;
  };

  const act = async (key: string, url: string, method: string, body: Record<string, unknown>) => {
    setBusy(key);
    setActionError(null);
    try {
      const res = await fetch(url, { method, headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) });
      if (!res.ok) {
        const payload = (await res.json().catch(() => ({}))) as { error?: string; violations?: Array<{ message: string }> };
        const detail = payload.violations?.map((v) => v.message).join(' ') ?? '';
        throw new Error(`${payload.error ?? `API ${res.status}`} ${detail}`.trim());
      }
      await load();
    } catch (e) {
      setActionError(e instanceof Error ? e.message : 'Action failed');
    } finally {
      setBusy(null);
    }
  };

  const decide = (link: StatementLink, decision: 'approve' | 'reject') => {
    if (!requireReviewer()) return;
    const note = window.prompt(decision === 'approve' ? 'Reviewer note (why is this statement relevant to this Context of Use?)' : 'Reviewer note (why is it not applicable / not comparable?)') ?? '';
    const body: Record<string, unknown> = { reviewed_by: reviewer.trim(), reviewer_note: note };
    if (decision === 'reject' && window.confirm('Mark this statement as NOT COMPARABLE with this Context of Use (instead of a plain rejection)?')) {
      body.relationship = 'not_comparable';
      body.comparability_note = note;
    }
    void act(link.id, `${API}/api/v1/projects/${id}/external-statement-links/${link.id}/${decision}`, 'PATCH', body);
  };

  const verify = (s: Statement) => {
    if (!requireReviewer()) return;
    void act(s.id, `${API}/api/v1/projects/${id}/external-statements/${s.id}/verify`, 'PATCH', { reviewed_by: reviewer.trim() });
  };

  const submitLink = (e: FormEvent) => {
    e.preventDefault();
    if (!linkForm || linkForm.targetId === '') return;
    void act('new-link', `${API}/api/v1/projects/${id}/external-statement-links`, 'POST', {
      external_statement_id: linkForm.statementId,
      target_type: linkForm.targetType,
      target_id: linkForm.targetId,
      relationship: linkForm.relationship,
      link_origin: 'human_entered',
      comparability_note: linkForm.note || null,
      created_by: reviewer.trim() || 'anonymous-ui',
    }).then(() => setLinkForm(null));
  };

  const targetOptions = useMemo(() => {
    if (!targets || !linkForm) return [] as Array<{ id: string; label: string }>;
    switch (linkForm.targetType) {
      case 'requirement_assessment':
        return targets.requirement_assessments.map((a) => ({ id: a.id, label: `${a.requirement_key} — ${a.title} (${humanize(a.status)})` }));
      case 'claim':
        return targets.claims.map((c) => ({ id: c.id, label: `${c.claim_id} — ${c.claim_text.slice(0, 90)}…` }));
      default:
        return targets.evidence_items.map((ev) => ({ id: ev.id, label: `${ev.evidence_id} — ${ev.question}` }));
    }
  }, [targets, linkForm]);

  return (
    <div className="p-8 max-w-6xl">
      <div className="mb-6 flex items-start justify-between gap-6">
        <div>
          <h1 className="text-2xl font-bold text-slate-900 flex items-center gap-2">
            <BookOpen className="w-6 h-6 text-blue-600" /> External Statements
          </h1>
          <p className="text-sm text-slate-500 mt-1 max-w-3xl">
            Statements captured from external evidence sources such as the TIB Knowledge Loom (DOI-identified statements with
            declared data and code links). The tool only <em>suggests</em> where a statement might be relevant. A named reviewer
            decides. Nothing here replaces the project&apos;s own validation evidence, changes a claim&apos;s review status, or
            moves the export gate.
          </p>
        </div>
        <div className="card p-4 w-72 flex-shrink-0">
          <label className="label" htmlFor="reviewer">Your reviewer identity</label>
          <input
            id="reviewer"
            className="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            placeholder="e.g. j.doe@sponsor.org"
            value={reviewer}
            onChange={(e) => setReviewer(e.target.value)}
          />
          <p className="text-[11px] text-slate-500 mt-2">Required for verify, approve and reject. Recorded in the audit trail.</p>
        </div>
      </div>

      {actionError && (
        <div className="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 flex items-start gap-2">
          <ShieldAlert className="w-4 h-4 mt-0.5 flex-shrink-0" /> <span>{actionError}</span>
        </div>
      )}

      {loading && <div className="text-slate-500">Loading external statements…</div>}
      {error && <div className="text-rose-600">Error: {error}</div>}

      {data && (
        <>
          <div className="grid grid-cols-2 md:grid-cols-5 gap-3 mb-6">
            {[
              ['Statements', data.summary.total],
              ['Captures verified', data.summary.captured_verified],
              ['Links awaiting decision', data.summary.links_suggested],
              ['Links approved', data.summary.links_approved],
              ['Marked not comparable', data.summary.links_not_comparable],
            ].map(([label, value]) => (
              <div key={String(label)} className="card p-4">
                <div className="label">{label}</div>
                <div className="text-2xl font-semibold text-slate-900">{value}</div>
              </div>
            ))}
          </div>

          {data.summary.restricted > 0 && (
            <div className="mb-6 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-900">
              {data.summary.restricted} restricted statement{data.summary.restricted > 1 ? 's are' : ' is'} held inside this project only. Restricted or
              unknown-scope statements are never written to JSON-LD, RO-Crate or any other export, and cannot be approved as supporting evidence.
            </div>
          )}

          {data.statements.length === 0 && (
            <div className="card p-8 text-center text-slate-500 text-sm">
              No external statements captured yet. Import a Knowledge Loom record through
              <code className="mx-1 px-1 bg-slate-100 rounded">POST /api/v1/projects/{'{id}'}/external-statements/import</code>
              or load the synthetic demo with <code className="mx-1 px-1 bg-slate-100 rounded">app:load-loom-demo</code>.
            </div>
          )}

          <div className="space-y-5">
            {data.statements.map((s) => (
              <div key={s.id} className="card p-5">
                <div className="flex flex-wrap items-center gap-2 mb-2">
                  <span className={badge(s.access_scope)}>{s.access_scope}</span>
                  <span className={badge(s.capture_status)}>{humanize(s.capture_status)}</span>
                  <span className="badge bg-slate-100 text-slate-600">{humanize(s.statement_kind)}</span>
                  <span className="badge bg-slate-100 text-slate-600">{s.source_system}</span>
                  {s.licence && <span className="badge bg-slate-100 text-slate-600">{s.licence}</span>}
                  {!s.exportable && <span className="badge bg-rose-50 text-rose-700">not exported</span>}
                </div>
                <p className="text-sm text-slate-800 leading-relaxed">{s.statement_text}</p>
                <div className="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-500">
                  <a className="inline-flex items-center gap-1 text-blue-700 hover:underline" href={s.statement_uri} target="_blank" rel="noreferrer">
                    <ExternalLink className="w-3 h-3" /> statement {s.statement_uri.replace('https://doi.org/', 'doi:')}
                  </a>
                  {s.record_uri && <span>record {s.record_uri.replace('https://doi.org/', 'doi:')}</span>}
                  <span>version {s.source_version}</span>
                  <span>captured by {s.captured_by}</span>
                  <span title={s.payload_sha256}>sha256 {s.payload_sha256.slice(0, 12)}…</span>
                  {s.verified_by && <span>verified by {s.verified_by}</span>}
                </div>

                {(s.dataset_links.length > 0 || Object.keys(s.study_context).length > 0) && (
                  <div className="mt-3 grid md:grid-cols-2 gap-3 text-xs">
                    {Object.keys(s.study_context).length > 0 && (
                      <div className="rounded-lg bg-slate-50 border border-slate-200 p-3">
                        <div className="label">Declared study context</div>
                        <dl className="grid grid-cols-[auto_1fr] gap-x-3 gap-y-0.5">
                          {Object.entries(s.study_context).map(([k, v]) => (
                            <div key={k} className="contents">
                              <dt className="text-slate-500">{humanize(k)}</dt>
                              <dd className="text-slate-800">{v}</dd>
                            </div>
                          ))}
                        </dl>
                      </div>
                    )}
                    <div className="rounded-lg bg-slate-50 border border-slate-200 p-3">
                      <div className="label">Data and code behind the statement</div>
                      {s.dataset_links.length === 0 && s.code_links.length === 0 && <div className="text-slate-500">None declared by the source.</div>}
                      <ul className="space-y-0.5">
                        {s.dataset_links.map((d) => (
                          <li key={d.uri}>
                            <a className="text-blue-700 hover:underline" href={d.uri} target="_blank" rel="noreferrer">{d.label ?? d.uri}</a>
                            {d.licence && <span className="text-slate-500"> · {d.licence}</span>}
                          </li>
                        ))}
                        {s.code_links.map((c) => (
                          <li key={c.uri}>
                            <a className="text-blue-700 hover:underline" href={c.uri} target="_blank" rel="noreferrer">{c.label ?? c.uri}</a>
                            <span className="text-slate-500"> · code</span>
                          </li>
                        ))}
                      </ul>
                      {Object.keys(s.uncertainty).length > 0 && (
                        <div className="mt-2 text-slate-600">
                          <span className="font-medium">Declared uncertainty:</span>{' '}
                          {Object.entries(s.uncertainty).map(([k, v]) => `${humanize(k)}: ${v}`).join('; ')}
                        </div>
                      )}
                    </div>
                  </div>
                )}

                <div className="mt-4 flex flex-wrap items-center gap-2">
                  {s.capture_status !== 'captured_verified' && (
                    <button className="btn-secondary" disabled={busy === s.id} onClick={() => verify(s)}>
                      <CheckCircle2 className="w-4 h-4" /> Confirm capture matches source
                    </button>
                  )}
                  <button
                    className="btn-secondary"
                    onClick={() => setLinkForm({ statementId: s.id, targetType: 'requirement_assessment', targetId: '', relationship: 'contextualizes', note: '' })}
                  >
                    <Link2 className="w-4 h-4" /> Link to a project record
                  </button>
                  <button className="btn-ghost text-xs" onClick={() => setOpenRaw({ ...openRaw, [s.id]: !openRaw[s.id] })}>
                    {openRaw[s.id] ? 'Hide' : 'Show'} raw capture
                  </button>
                </div>
                {openRaw[s.id] && (
                  <pre className="mt-3 text-[11px] bg-slate-900 text-slate-100 rounded-lg p-3 overflow-x-auto">{JSON.stringify(s.raw_payload, null, 2)}</pre>
                )}

                {linkForm?.statementId === s.id && (
                  <form onSubmit={submitLink} className="mt-4 rounded-lg border border-blue-200 bg-blue-50 p-4 grid md:grid-cols-4 gap-3 items-end">
                    <div>
                      <label className="label">Target type</label>
                      <select
                        className="w-full border border-slate-300 rounded-lg px-2 py-2 text-sm"
                        value={linkForm.targetType}
                        onChange={(e) => setLinkForm({ ...linkForm, targetType: e.target.value as TargetType, targetId: '' })}
                      >
                        <option value="requirement_assessment">Guidance requirement assessment</option>
                        <option value="claim">Claim</option>
                        <option value="evidence_item">Validation evidence row</option>
                      </select>
                    </div>
                    <div className="md:col-span-2">
                      <label className="label">Target</label>
                      <select
                        className="w-full border border-slate-300 rounded-lg px-2 py-2 text-sm"
                        value={linkForm.targetId}
                        onChange={(e) => setLinkForm({ ...linkForm, targetId: e.target.value })}
                      >
                        <option value="">Select…</option>
                        {targetOptions.map((o) => (
                          <option key={o.id} value={o.id}>{o.label}</option>
                        ))}
                      </select>
                    </div>
                    <div>
                      <label className="label">Relationship</label>
                      <select
                        className="w-full border border-slate-300 rounded-lg px-2 py-2 text-sm"
                        value={linkForm.relationship}
                        onChange={(e) => setLinkForm({ ...linkForm, relationship: e.target.value as Relationship })}
                      >
                        {RELATIONSHIPS.map((r) => <option key={r} value={r}>{humanize(r)}</option>)}
                      </select>
                    </div>
                    <div className="md:col-span-3">
                      <label className="label">Comparability note (population, exposure, comparator, outcome, unit, timing)</label>
                      <input
                        className="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm"
                        value={linkForm.note}
                        onChange={(e) => setLinkForm({ ...linkForm, note: e.target.value })}
                      />
                    </div>
                    <div className="flex gap-2">
                      <button type="submit" className="btn-primary" disabled={busy === 'new-link' || linkForm.targetId === ''}>Suggest link</button>
                      <button type="button" className="btn-ghost" onClick={() => setLinkForm(null)}>Cancel</button>
                    </div>
                    <p className="md:col-span-4 text-[11px] text-blue-900">
                      A new link is created as a <strong>suggestion</strong>. It counts for nothing until a named reviewer approves it below.
                    </p>
                  </form>
                )}

                <div className="mt-4">
                  <div className="label">Links to this project ({s.links.length})</div>
                  {s.links.length === 0 && <div className="text-xs text-slate-500">No links. This statement is captured but not connected to anything in the project.</div>}
                  <ul className="divide-y divide-slate-100 border border-slate-200 rounded-lg">
                    {s.links.map((l) => (
                      <li key={l.id} className="p-3 flex flex-wrap items-start gap-3 text-sm">
                        <div className="flex-1 min-w-[240px]">
                          <div className="flex flex-wrap items-center gap-2 mb-1">
                            <span className={relationshipBadge(l.relationship)}>{humanize(l.relationship)}</span>
                            <span className={badge(l.review_status)}>{l.review_status}</span>
                            <span className="badge bg-slate-100 text-slate-600">{humanize(l.link_origin)}</span>
                            <span className="text-xs text-slate-500">→ {humanize(l.target_type)}</span>
                          </div>
                          <div className="text-slate-800">
                            <span className="font-medium">{l.target.key}</span>
                            {l.target.label && <span className="text-slate-600"> — {l.target.label}</span>}
                          </div>
                          {typeof l.provenance.rule === 'string' && l.review_status === 'suggested' && (
                            <div className="text-xs text-slate-500 mt-1">Suggested because: {l.provenance.rule}</div>
                          )}
                          {l.comparability_note && <div className="text-xs text-slate-600 mt-1">Comparability: {l.comparability_note}</div>}
                          {l.reviewer_note && <div className="text-xs text-slate-600 mt-1">Reviewer: {l.reviewer_note}</div>}
                          {l.reviewed_by && (
                            <div className="text-[11px] text-slate-400 mt-1">
                              {l.review_status} by {l.reviewed_by}{l.reviewed_at ? ` on ${new Date(l.reviewed_at).toLocaleDateString()}` : ''}
                            </div>
                          )}
                        </div>
                        {l.review_status === 'suggested' && (
                          <div className="flex gap-2">
                            <button className="btn-primary" disabled={busy === l.id} onClick={() => decide(l, 'approve')}>
                              <CheckCircle2 className="w-4 h-4" /> Approve
                            </button>
                            <button className="btn-secondary" disabled={busy === l.id} onClick={() => decide(l, 'reject')}>
                              <XCircle className="w-4 h-4" /> Reject
                            </button>
                          </div>
                        )}
                      </li>
                    ))}
                  </ul>
                </div>
              </div>
            ))}
          </div>

          <p className="mt-6 text-xs text-slate-500 border-t border-slate-200 pt-4">{data.interpretation_note}</p>
        </>
      )}
    </div>
  );
}
