'use client';

import { useCallback, useEffect, useState } from 'react';
import { useParams } from 'next/navigation';
import { ChevronDown, ChevronRight } from 'lucide-react';

const API = process.env.NEXT_PUBLIC_API_URL ?? 'http://localhost:8080';

interface ValidationIssue {
  severity: string;
  blocking: boolean;
  rule: string;
  workspace: string;
  entity: string;
  field: string;
  message: string;
  recommended_fix: string;
}

interface ShaclSidecar {
  available: boolean;
  [key: string]: unknown;
}

interface SemanticValidation {
  conforms: boolean;
  error_count: number;
  warning_count: number;
  blocking_count: number;
  completion_percentage: number;
  errors: ValidationIssue[];
  warnings: ValidationIssue[];
  issues: ValidationIssue[];
  shacl_sidecar: ShaclSidecar;
}

function groupByWorkspace(issues: ValidationIssue[]): Record<string, ValidationIssue[]> {
  return issues.reduce<Record<string, ValidationIssue[]>>((acc, issue) => {
    const key = issue.workspace || 'general';
    (acc[key] ??= []).push(issue);
    return acc;
  }, {});
}

export default function SemanticValidationPage() {
  const { id } = useParams<{ id: string }>();
  const [data, setData] = useState<SemanticValidation | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [collapsed, setCollapsed] = useState<Record<string, boolean>>({});

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await fetch(`${API}/api/v1/projects/${id}/semantic-validation`, {
        cache: 'no-store',
      });
      if (!res.ok) throw new Error(`API ${res.status}`);
      setData((await res.json()) as SemanticValidation);
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Failed to load validation');
    } finally {
      setLoading(false);
    }
  }, [id]);

  useEffect(() => {
    void load();
  }, [load]);

  const toggle = (key: string) =>
    setCollapsed((prev) => ({ ...prev, [key]: !prev[key] }));

  return (
    <div className="p-8 max-w-6xl">
      <div className="mb-6">
        <h1 className="text-2xl font-bold text-slate-900">Semantic Validation</h1>
        <p className="text-sm text-slate-500 mt-1">
          SHACL-style constraint checks across NAM-CORE workspaces.
        </p>
        <p className="text-xs text-amber-700 mt-2">
          POC standardization — requires qualified human review. Not an official submission standard.
        </p>
      </div>

      {loading ? (
        <p className="text-sm text-slate-500">Loading validation…</p>
      ) : error ? (
        <p className="text-sm text-rose-700">{error}</p>
      ) : !data ? (
        <p className="text-sm text-slate-500">No data.</p>
      ) : (
        <>
          {/* Header status */}
          <div className="rounded-xl border border-slate-200 bg-white p-5 mb-6">
            <div className="flex items-center gap-3 mb-3">
              <span
                className={`badge text-xs ${
                  data.conforms ? 'bg-green-100 text-green-700' : 'bg-rose-100 text-rose-700'
                }`}
              >
                {data.conforms ? 'Conforms' : 'Does not conform'}
              </span>
              <span className="text-xs text-rose-700">Errors: {data.error_count}</span>
              <span className="text-xs text-amber-700">Warnings: {data.warning_count}</span>
              <span className="text-xs text-slate-600">Blocking: {data.blocking_count}</span>
            </div>

            <div className="mb-1 flex items-center justify-between text-xs text-slate-600">
              <span>Completion</span>
              <span>{Math.round(data.completion_percentage)}%</span>
            </div>
            <div className="w-full h-2 rounded-full bg-slate-100 overflow-hidden">
              <div
                className="h-full bg-blue-600"
                style={{ width: `${Math.min(100, Math.max(0, data.completion_percentage))}%` }}
              />
            </div>

            <p className="text-xs text-slate-500 mt-3">
              pyshacl sidecar:{' '}
              {data.shacl_sidecar?.available ? (
                <span className="text-green-700 font-medium">used</span>
              ) : (
                <span className="text-slate-500">not available (heuristic checks only)</span>
              )}
            </p>
          </div>

          <IssueGroups title="Errors" tone="rose" issues={data.errors} collapsed={collapsed} toggle={toggle} />
          <IssueGroups title="Warnings" tone="amber" issues={data.warnings} collapsed={collapsed} toggle={toggle} />
        </>
      )}
    </div>
  );
}

function IssueGroups({
  title,
  tone,
  issues,
  collapsed,
  toggle,
}: {
  title: string;
  tone: 'rose' | 'amber';
  issues: ValidationIssue[];
  collapsed: Record<string, boolean>;
  toggle: (key: string) => void;
}) {
  if (issues.length === 0) return null;
  const grouped = groupByWorkspace(issues);
  const headText = tone === 'rose' ? 'text-rose-700' : 'text-amber-700';

  return (
    <div className="mb-6">
      <h2 className={`text-sm font-semibold mb-3 ${headText}`}>
        {title} ({issues.length})
      </h2>
      <div className="space-y-2">
        {Object.entries(grouped).map(([workspace, list]) => {
          const key = `${title}:${workspace}`;
          const isCollapsed = collapsed[key] ?? false;
          return (
            <div key={key} className="rounded-xl border border-slate-200 bg-white">
              <button
                className="w-full flex items-center gap-2 px-4 py-2.5 text-left"
                onClick={() => toggle(key)}
              >
                {isCollapsed ? (
                  <ChevronRight className="w-4 h-4 text-slate-400" />
                ) : (
                  <ChevronDown className="w-4 h-4 text-slate-400" />
                )}
                <span className="text-sm font-medium text-slate-700">{workspace}</span>
                <span className="badge bg-slate-100 text-slate-600 text-xs">{list.length}</span>
              </button>
              {!isCollapsed ? (
                <div className="px-4 pb-3 overflow-x-auto">
                  <table className="w-full text-xs border border-slate-200">
                    <thead className="bg-slate-50">
                      <tr>
                        <th className="px-2 py-1.5 text-left font-medium text-slate-600">Rule</th>
                        <th className="px-2 py-1.5 text-left font-medium text-slate-600">Entity</th>
                        <th className="px-2 py-1.5 text-left font-medium text-slate-600">Field</th>
                        <th className="px-2 py-1.5 text-left font-medium text-slate-600">Message</th>
                        <th className="px-2 py-1.5 text-left font-medium text-slate-600">Recommended fix</th>
                        <th className="px-2 py-1.5 text-left font-medium text-slate-600">Blocking</th>
                      </tr>
                    </thead>
                    <tbody>
                      {list.map((issue, i) => (
                        <tr key={i} className="odd:bg-white even:bg-slate-50">
                          <td className="px-2 py-1.5 text-slate-700 font-mono">{issue.rule}</td>
                          <td className="px-2 py-1.5 text-slate-700">{issue.entity}</td>
                          <td className="px-2 py-1.5 text-slate-700 font-mono">{issue.field}</td>
                          <td className="px-2 py-1.5 text-slate-700">{issue.message}</td>
                          <td className="px-2 py-1.5 text-slate-600">{issue.recommended_fix}</td>
                          <td className="px-2 py-1.5">
                            {issue.blocking ? (
                              <span className="badge bg-rose-100 text-rose-700 text-xs">blocking</span>
                            ) : (
                              <span className="text-slate-400">—</span>
                            )}
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              ) : null}
            </div>
          );
        })}
      </div>
    </div>
  );
}
