'use client';

import { useCallback, useEffect, useMemo, useState } from 'react';
import { useParams } from 'next/navigation';
import { useStore } from '@/lib/store';
import Link from 'next/link';
import ConfidenceBadge from '@/components/ConfidenceBadge';
import StatusBadge from '@/components/StatusBadge';
import {
  AlertTriangle,
  ArrowRight,
  Boxes,
  CheckCircle2,
  Database,
  FileSpreadsheet,
  FileText,
  FolderTree,
  Gauge,
  GitBranch,
  Network,
  ShieldCheck,
  Sparkles,
  Table,
  Workflow,
} from 'lucide-react';

const API = process.env.NEXT_PUBLIC_API_URL ?? 'http://localhost:8080';
type OverviewIcon = typeof Table;

interface EndpointResponse {
  count: number;
  measurements?: Array<{ unresolved?: boolean; raw_file?: string | null }>;
}

interface MappingSummary {
  total: number;
  approved: number;
  mandatory_unresolved: number;
}

interface MappingResponse {
  summary: MappingSummary;
}

interface SemanticResponse {
  error_count: number;
  warning_count: number;
  blocking_count: number;
  issues?: Array<{
    workspace: string;
    message: string;
    recommended_fix: string;
    blocking: boolean;
  }>;
}

interface ReadinessResponse {
  label: string;
  percentage: number;
}

interface StandardizationSnapshot {
  endpoints: EndpointResponse | null;
  mappings: MappingSummary | null;
  semantic: SemanticResponse | null;
  readiness: ReadinessResponse | null;
}

async function loadJson<T>(path: string): Promise<T | null> {
  try {
    const res = await fetch(`${API}${path}`, { cache: 'no-store' });
    if (!res.ok) return null;
    return (await res.json()) as T;
  } catch {
    return null;
  }
}

function percent(value: number, max: number): number {
  return max <= 0 ? 0 : Math.round((value / max) * 100);
}

function workspaceHref(projectId: string, workspace: string): string {
  const routes: Record<string, string> = {
    context_of_use: 'cou',
    nam_study: 'study',
    endpoints: 'endpoints',
    claims: 'claims',
    provenance: 'provenance',
    ontology: 'ontology',
    readiness: 'readiness',
    audit: 'audit',
  };

  return `/projects/${projectId}/${routes[workspace] ?? 'semantic-validation'}`;
}

export default function ProjectOverviewPage() {
  const { id } = useParams<{ id: string }>();
  const { getProject, getCOU, getStudy, getEvidenceItems, getClaimNodes } = useStore();
  const [snapshot, setSnapshot] = useState<StandardizationSnapshot>({
    endpoints: null,
    mappings: null,
    semantic: null,
    readiness: null,
  });

  const project = getProject(id);
  const cou = getCOU(id);
  const study = getStudy(id);
  const evidence = useMemo(() => (study ? getEvidenceItems(study.study_id) : []), [getEvidenceItems, study]);
  const claims = useMemo(() => getClaimNodes(id), [getClaimNodes, id]);

  const loadSnapshot = useCallback(async () => {
    const [endpoints, mappings, semantic, readiness] = await Promise.all([
      loadJson<EndpointResponse>(`/api/v1/projects/${id}/endpoint-measurements`),
      loadJson<MappingResponse>(`/api/v1/projects/${id}/ontology-mappings`),
      loadJson<SemanticResponse>(`/api/v1/projects/${id}/semantic-validation`),
      loadJson<ReadinessResponse>(`/api/v1/projects/${id}/readiness-report`),
    ]);

    setSnapshot({
      endpoints,
      mappings: mappings?.summary ?? null,
      semantic,
      readiness,
    });
  }, [id]);

  useEffect(() => {
    void loadSnapshot();
  }, [loadSnapshot]);

  const derived = useMemo(() => {
    const evidenceMet = evidence.filter((e) => e.status === 'met').length;
    const claimsApproved = claims.filter((c) => c.review_status === 'approved').length;
    const pendingClaims = claims.filter((c) => c.review_status === 'human_review_required');
    const endpointCount = snapshot.endpoints?.count ?? 0;
    const unresolvedMeasurements =
      snapshot.endpoints?.measurements?.filter((m) => m.unresolved || !m.raw_file).length ?? 0;
    const ontologyApproved = snapshot.mappings?.approved ?? 0;
    const ontologyTotal = snapshot.mappings?.total ?? 0;
    const mandatoryUnresolved = snapshot.mappings?.mandatory_unresolved ?? 0;
    const blockingCount = (snapshot.semantic?.blocking_count ?? 0) + pendingClaims.length + mandatoryUnresolved;
    const readinessPct =
      snapshot.readiness?.percentage ??
      Math.round((percent(evidenceMet, Math.max(evidence.length, 1)) + percent(claimsApproved, Math.max(claims.length, 1))) / 2);

    const semanticBlockers = (snapshot.semantic?.issues ?? [])
      .filter((issue) => issue.blocking)
      .slice(0, 3)
      .map((issue) => ({
        label: issue.workspace.replace(/_/g, ' '),
        message: issue.message,
        fix: issue.recommended_fix,
        href: workspaceHref(id, issue.workspace),
      }));

    const topBlockers = [
      ...semanticBlockers,
      ...pendingClaims.slice(0, 2).map((claim) => ({
        label: 'claims',
        message: `${claim.claim_id} requires human review`,
        fix: 'Review the claim wording, evidence, and limitations.',
        href: `/projects/${id}/claims`,
      })),
      ...(mandatoryUnresolved > 0
        ? [
            {
              label: 'ontology',
              message: `${mandatoryUnresolved} mandatory ontology mapping(s) unresolved`,
              fix: 'Approve or correct required mappings.',
              href: `/projects/${id}/ontology`,
            },
          ]
        : []),
    ].slice(0, 4);

    return {
      evidenceMet,
      claimsApproved,
      pendingClaims,
      endpointCount,
      unresolvedMeasurements,
      ontologyApproved,
      ontologyTotal,
      mandatoryUnresolved,
      blockingCount,
      readinessPct,
      topBlockers,
    };
  }, [claims, evidence, id, snapshot]);

  if (!project) {
    return (
      <div className="p-8 text-center text-slate-500">
        Project not found.{' '}
        <Link href="/" className="text-blue-600 underline">
          Go back
        </Link>
      </div>
    );
  }

  const readinessLabel = snapshot.readiness?.label ?? 'Evidence package maturity';
  const outputFormats: Array<{ label: string; icon: OverviewIcon }> = [
    { label: 'JSON-LD', icon: FileText },
    { label: 'RDF/Turtle', icon: Network },
    { label: 'RO-Crate', icon: Boxes },
    { label: 'ISA-Tab', icon: FileSpreadsheet },
    { label: 'Parquet', icon: Database },
    { label: 'eCTD map', icon: FolderTree },
  ];

  return (
    <div className="min-h-screen bg-slate-50">
      <div className="border-b border-slate-200 bg-white">
        <div className="max-w-7xl px-8 py-7">
          <div className="flex flex-wrap items-start justify-between gap-5">
            <div className="max-w-3xl">
              <div className="mb-3 flex flex-wrap items-center gap-2">
                <span className="badge bg-blue-100 text-blue-700">NAM-CORE standardization</span>
                {cou ? <ConfidenceBadge level={cou.regulatory_confidence_level} size="sm" /> : null}
                <StatusBadge status={project.review_status} />
              </div>
              <h1 className="text-3xl font-bold text-slate-950">Before spreadsheet fragments. After an evidence package.</h1>
              <p className="mt-3 max-w-2xl text-sm leading-6 text-slate-600">
                Before standardization, a NAM study is a spreadsheet, a protocol note, and a regulatory argument. After, it is a validated, ontology-linked, provenance-aware, context-of-use evidence package for scientists, reviewers, and AI pipelines.
              </p>
            </div>
            <div className="w-full max-w-xs rounded-lg border border-slate-200 bg-slate-50 p-4">
              <div className="flex items-center justify-between">
                <span className="text-xs font-medium uppercase text-slate-500">FAIR / AI-readiness</span>
                <Gauge className="h-4 w-4 text-blue-600" />
              </div>
              <div className="mt-2 flex items-end gap-2">
                <span className="text-4xl font-bold text-slate-950">{Math.round(derived.readinessPct)}%</span>
                <span className="pb-1 text-xs text-slate-500">{readinessLabel}</span>
              </div>
              <div className="mt-3 h-2 overflow-hidden rounded-full bg-white">
                <div className="h-full rounded-full bg-blue-600" style={{ width: `${Math.min(100, Math.max(0, derived.readinessPct))}%` }} />
              </div>
              <p className="mt-3 text-xs text-amber-700">POC readiness score. Requires qualified human review.</p>
            </div>
          </div>
        </div>
      </div>

      <main className="max-w-7xl space-y-6 px-8 py-7">
        <section className="grid gap-4 lg:grid-cols-3">
          <BeforeAfterPanel />
          <StandardizationPipeline id={id} />
        </section>

        <section className="grid gap-4 lg:grid-cols-4">
          <MetricCard icon={Table} label="Endpoint rows standardized" value={String(derived.endpointCount)} detail={`${derived.unresolvedMeasurements} unresolved provenance or validation flag(s)`} tone={derived.unresolvedMeasurements > 0 ? 'amber' : 'green'} href={`/projects/${id}/endpoints`} />
          <MetricCard icon={Network} label="Ontology mappings approved" value={`${derived.ontologyApproved}/${derived.ontologyTotal}`} detail={`${derived.mandatoryUnresolved} mandatory unresolved`} tone={derived.mandatoryUnresolved > 0 ? 'rose' : 'green'} href={`/projects/${id}/ontology`} />
          <MetricCard icon={ShieldCheck} label="Semantic blockers" value={String(derived.blockingCount)} detail={`${snapshot.semantic?.error_count ?? 0} errors · ${snapshot.semantic?.warning_count ?? 0} warnings`} tone={derived.blockingCount > 0 ? 'rose' : 'green'} href={`/projects/${id}/semantic-validation`} />
          <MetricCard icon={GitBranch} label="Human-reviewed claims" value={`${derived.claimsApproved}/${claims.length}`} detail={`${derived.pendingClaims.length} claim(s) pending review`} tone={derived.pendingClaims.length > 0 ? 'amber' : 'green'} href={`/projects/${id}/claims`} />
        </section>

        <section className="grid gap-4 lg:grid-cols-[1.2fr_0.8fr]">
          <div className="rounded-lg border border-slate-200 bg-white p-5">
            <div className="mb-4 flex items-center justify-between gap-3">
              <div>
                <h2 className="text-base font-semibold text-slate-900">Top blockers</h2>
                <p className="text-sm text-slate-500">The fastest route from demo data to an inspectable package.</p>
              </div>
              <Link href={`/projects/${id}/semantic-validation`} className="btn-secondary py-1.5 text-xs">
                Open validation
                <ArrowRight className="h-3.5 w-3.5" />
              </Link>
            </div>
            {derived.topBlockers.length > 0 ? (
              <div className="space-y-3">
                {derived.topBlockers.map((blocker, index) => (
                  <Link key={`${blocker.label}:${index}`} href={blocker.href} className="block rounded-lg border border-slate-200 p-3 transition-colors hover:border-blue-300 hover:bg-blue-50/40">
                    <div className="flex items-start gap-3">
                      <div className="mt-0.5 flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-lg bg-amber-100 text-amber-700">
                        <AlertTriangle className="h-4 w-4" />
                      </div>
                      <div>
                        <div className="text-xs font-semibold uppercase text-slate-500">{blocker.label}</div>
                        <div className="mt-0.5 text-sm font-medium text-slate-900">{blocker.message}</div>
                        <div className="mt-1 text-xs text-slate-500">{blocker.fix}</div>
                      </div>
                    </div>
                  </Link>
                ))}
              </div>
            ) : (
              <div className="rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-800">
                <CheckCircle2 className="mr-2 inline h-4 w-4" />
                No top blockers detected from the current project state.
              </div>
            )}
          </div>

          <div className="rounded-lg border border-slate-200 bg-white p-5">
            <h2 className="text-base font-semibold text-slate-900">Reusable package outputs</h2>
            <p className="mt-1 text-sm text-slate-500">The payoff is a package that can be inspected by people and parsed by downstream systems.</p>
            <div className="mt-4 grid grid-cols-2 gap-3">
              {outputFormats.map(({ label, icon: Icon }) => (
                <div key={label} className="rounded-lg border border-slate-200 bg-slate-50 p-3">
                  <Icon className="mb-2 h-4 w-4 text-slate-600" />
                  <div className="text-sm font-medium text-slate-800">{label}</div>
                </div>
              ))}
            </div>
            <Link href={`/projects/${id}/export`} className="btn-primary mt-4 w-full justify-center">
              Open Export Center
              <ArrowRight className="h-4 w-4" />
            </Link>
          </div>
        </section>

        <section className="rounded-lg border border-slate-200 bg-white p-5">
          <div className="mb-4 flex items-center gap-2">
            <Sparkles className="h-5 w-5 text-blue-600" />
            <h2 className="text-base font-semibold text-slate-900">Demo path for the three audiences</h2>
          </div>
          <div className="grid gap-3 md:grid-cols-3">
            <AudienceCard title="Scientist" text="Inspect endpoint rows, units, assays, samples, and provenance before trusting derived conclusions." href={`/projects/${id}/endpoints`} />
            <AudienceCard title="AI pipeline" text="Approve ontology links and export JSON-LD, RDF/Turtle, Parquet, ISA-Tab, or RO-Crate." href={`/projects/${id}/ontology`} />
            <AudienceCard title="Reviewer" text="Use blockers, readiness, claims, audit, and eCTD mapping to see what is defensible and what still needs review." href={`/projects/${id}/readiness`} />
          </div>
        </section>
      </main>
    </div>
  );
}

function BeforeAfterPanel() {
  return (
    <div className="rounded-lg border border-slate-200 bg-white p-5 lg:col-span-1">
      <h2 className="text-base font-semibold text-slate-900">Before to after</h2>
      <div className="mt-4 space-y-3">
        <div className="rounded-lg border border-rose-200 bg-rose-50 p-3">
          <div className="text-xs font-semibold uppercase text-rose-700">Before</div>
          <div className="mt-1 text-sm text-rose-950">Spreadsheet rows, protocol notes, free-text terms, and unreviewed regulatory claims.</div>
        </div>
        <div className="flex justify-center">
          <ArrowRight className="h-5 w-5 text-slate-400" />
        </div>
        <div className="rounded-lg border border-green-200 bg-green-50 p-3">
          <div className="text-xs font-semibold uppercase text-green-700">After</div>
          <div className="mt-1 text-sm text-green-950">Canonical endpoint measurements, approved ontology links, validation blockers, provenance, readiness, and exports.</div>
        </div>
      </div>
    </div>
  );
}

function StandardizationPipeline({ id }: { id: string }) {
  const steps = [
    { label: 'CSV import', icon: FileSpreadsheet, href: `/projects/${id}/endpoints` },
    { label: 'Column mapping', icon: Table, href: `/projects/${id}/endpoints` },
    { label: 'Ontology approval', icon: Network, href: `/projects/${id}/ontology` },
    { label: 'Validation gates', icon: ShieldCheck, href: `/projects/${id}/semantic-validation` },
    { label: 'Readiness', icon: Gauge, href: `/projects/${id}/readiness` },
    { label: 'Provenance', icon: Workflow, href: `/projects/${id}/provenance` },
  ];

  return (
    <div className="rounded-lg border border-slate-200 bg-white p-5 lg:col-span-2">
      <h2 className="text-base font-semibold text-slate-900">Raw to canonical standardization workflow</h2>
      <div className="mt-4 grid gap-3 md:grid-cols-3">
        {steps.map((step, index) => {
          const Icon = step.icon;
          return (
            <Link key={step.label} href={step.href} className="group rounded-lg border border-slate-200 bg-slate-50 p-4 transition-colors hover:border-blue-300 hover:bg-blue-50">
              <div className="flex items-center justify-between">
                <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-white text-blue-600">
                  <Icon className="h-4 w-4" />
                </div>
                <span className="text-xs font-mono text-slate-400">0{index + 1}</span>
              </div>
              <div className="mt-3 text-sm font-semibold text-slate-900 group-hover:text-blue-700">{step.label}</div>
            </Link>
          );
        })}
      </div>
    </div>
  );
}

function MetricCard({
  icon: Icon,
  label,
  value,
  detail,
  tone,
  href,
}: {
  icon: React.ComponentType<{ className?: string }>;
  label: string;
  value: string;
  detail: string;
  tone: 'green' | 'amber' | 'rose';
  href: string;
}) {
  const toneClasses = {
    green: 'bg-green-100 text-green-700',
    amber: 'bg-amber-100 text-amber-700',
    rose: 'bg-rose-100 text-rose-700',
  };

  return (
    <Link href={href} className="rounded-lg border border-slate-200 bg-white p-4 transition-colors hover:border-blue-300 hover:bg-blue-50/40">
      <div className="flex items-start justify-between gap-3">
        <div className={`flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-lg ${toneClasses[tone]}`}>
          <Icon className="h-4 w-4" />
        </div>
        <ArrowRight className="mt-1 h-4 w-4 text-slate-300" />
      </div>
      <div className="mt-3 text-2xl font-bold text-slate-950">{value}</div>
      <div className="mt-1 text-sm font-medium text-slate-800">{label}</div>
      <div className="mt-1 text-xs text-slate-500">{detail}</div>
    </Link>
  );
}

function AudienceCard({ title, text, href }: { title: string; text: string; href: string }) {
  return (
    <Link href={href} className="rounded-lg border border-slate-200 bg-slate-50 p-4 transition-colors hover:border-blue-300 hover:bg-blue-50">
      <div className="text-sm font-semibold text-slate-900">{title}</div>
      <p className="mt-2 text-sm leading-5 text-slate-600">{text}</p>
    </Link>
  );
}
