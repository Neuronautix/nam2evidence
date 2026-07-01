'use client';

import { useCallback, useEffect, useState } from 'react';
import { useParams } from 'next/navigation';

const API = process.env.NEXT_PUBLIC_API_URL ?? 'http://localhost:8080';

interface Dimension {
  key: string;
  label: string;
  score: number;
  max: number;
  rationale: string;
  improvement: string;
}

interface RecommendedImprovement {
  dimension: string;
  action: string;
}

interface ValidationSummary {
  errors: number;
  warnings: number;
  blocking: number;
}

interface ReadinessReport {
  label: string;
  disclaimer: string;
  total_score: number;
  max_score: number;
  percentage: number;
  dimensions: Dimension[];
  blocking_gaps: string[];
  recommended_improvements: RecommendedImprovement[];
  validation_summary: ValidationSummary;
}

function scoreColor(score: number): string {
  if (score >= 2) return 'bg-green-500';
  if (score === 1) return 'bg-amber-500';
  return 'bg-rose-500';
}

export default function ReadinessPage() {
  const { id } = useParams<{ id: string }>();
  const [data, setData] = useState<ReadinessReport | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await fetch(`${API}/api/v1/projects/${id}/readiness-report`, {
        cache: 'no-store',
      });
      if (!res.ok) throw new Error(`API ${res.status}`);
      setData((await res.json()) as ReadinessReport);
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Failed to load readiness report');
    } finally {
      setLoading(false);
    }
  }, [id]);

  useEffect(() => {
    void load();
  }, [load]);

  return (
    <div className="p-8 max-w-5xl">
      <div className="mb-6">
        <h1 className="text-2xl font-bold text-slate-900">Readiness Dashboard</h1>
        <p className="text-sm text-slate-500 mt-1">
          Multi-dimensional readiness assessment for NAM-CORE standardization.
        </p>
      </div>

      {loading ? (
        <p className="text-sm text-slate-500">Loading readiness report…</p>
      ) : error ? (
        <p className="text-sm text-rose-700">{error}</p>
      ) : !data ? (
        <p className="text-sm text-slate-500">No data.</p>
      ) : (
        <>
          {/* Label + score */}
          <div className="rounded-xl border border-slate-200 bg-white p-5 mb-6">
            <div className="flex items-center justify-between mb-2">
              <div>
                <div className="text-lg font-bold text-slate-900">{data.label}</div>
                <div className="text-sm text-slate-500">
                  Score {data.total_score} / {data.max_score} ({Math.round(data.percentage)}%)
                </div>
              </div>
              <div className="text-3xl font-bold text-blue-600">{Math.round(data.percentage)}%</div>
            </div>
            <div className="w-full h-2 rounded-full bg-slate-100 overflow-hidden mb-3">
              <div
                className="h-full bg-blue-600"
                style={{ width: `${Math.min(100, Math.max(0, data.percentage))}%` }}
              />
            </div>
            <p className="text-xs text-amber-700">{data.disclaimer}</p>
          </div>

          {/* Dimensions */}
          <div className="rounded-xl border border-slate-200 bg-white p-5 mb-6">
            <h2 className="text-sm font-semibold text-slate-800 mb-4">Dimensions</h2>
            <div className="space-y-4">
              {data.dimensions.map((d) => (
                <div key={d.key}>
                  <div className="flex items-center justify-between text-sm mb-1">
                    <span className="font-medium text-slate-700">{d.label}</span>
                    <span className="text-slate-500">
                      {d.score} / {d.max}
                    </span>
                  </div>
                  <div className="w-full h-2 rounded-full bg-slate-100 overflow-hidden">
                    <div
                      className={`h-full ${scoreColor(d.score)}`}
                      style={{ width: `${d.max > 0 ? (d.score / d.max) * 100 : 0}%` }}
                    />
                  </div>
                  <p className="text-xs text-slate-500 mt-1">{d.rationale}</p>
                  {d.improvement ? (
                    <p className="text-xs text-slate-400 mt-0.5">Improvement: {d.improvement}</p>
                  ) : null}
                </div>
              ))}
            </div>
          </div>

          {/* Validation summary */}
          <div className="flex flex-wrap gap-4 text-sm mb-6">
            <span className="text-rose-700">Errors: {data.validation_summary.errors}</span>
            <span className="text-amber-700">Warnings: {data.validation_summary.warnings}</span>
            <span className="text-slate-600">Blocking: {data.validation_summary.blocking}</span>
          </div>

          {/* Blocking gaps */}
          {data.blocking_gaps.length > 0 ? (
            <div className="rounded-xl border border-rose-200 bg-rose-50 p-5 mb-6">
              <h2 className="text-sm font-semibold text-rose-800 mb-2">Blocking gaps</h2>
              <ul className="list-disc list-inside text-sm text-rose-700 space-y-1">
                {data.blocking_gaps.map((gap, i) => (
                  <li key={i}>{gap}</li>
                ))}
              </ul>
            </div>
          ) : null}

          {/* Recommended improvements */}
          {data.recommended_improvements.length > 0 ? (
            <div className="rounded-xl border border-slate-200 bg-white p-5">
              <h2 className="text-sm font-semibold text-slate-800 mb-2">Recommended improvements</h2>
              <ul className="space-y-2">
                {data.recommended_improvements.map((r, i) => (
                  <li key={i} className="text-sm text-slate-700">
                    <span className="badge bg-slate-100 text-slate-600 text-xs mr-2">{r.dimension}</span>
                    {r.action}
                  </li>
                ))}
              </ul>
            </div>
          ) : null}
        </>
      )}
    </div>
  );
}
