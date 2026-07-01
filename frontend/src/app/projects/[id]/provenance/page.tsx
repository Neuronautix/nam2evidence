'use client';

import { useCallback, useEffect, useState } from 'react';
import { useParams } from 'next/navigation';

const API = process.env.NEXT_PUBLIC_API_URL ?? 'http://localhost:8080';

interface Measurement {
  id: string;
  endpoint_id: string;
  endpoint_label: string;
  assay: string | null;
  study: string | null;
  raw_file: string | null;
  unresolved: boolean;
}

interface MeasurementsResponse {
  count: number;
  measurements: Measurement[];
}

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

interface SemanticValidation {
  errors: ValidationIssue[];
  warnings: ValidationIssue[];
  issues: ValidationIssue[];
}

function isProvenanceIssue(issue: ValidationIssue): boolean {
  const haystack = `${issue.workspace} ${issue.rule} ${issue.field} ${issue.message}`.toLowerCase();
  return haystack.includes('provenance') || haystack.includes('lineage') || haystack.includes('raw');
}

export default function ProvenancePage() {
  const { id } = useParams<{ id: string }>();

  const [measurements, setMeasurements] = useState<Measurement[]>([]);
  const [provIssues, setProvIssues] = useState<ValidationIssue[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const [measRes, valRes] = await Promise.all([
        fetch(`${API}/api/v1/projects/${id}/endpoint-measurements`, { cache: 'no-store' }),
        fetch(`${API}/api/v1/projects/${id}/semantic-validation`, { cache: 'no-store' }),
      ]);
      if (!measRes.ok) throw new Error(`Measurements API ${measRes.status}`);
      const measData = (await measRes.json()) as MeasurementsResponse;
      setMeasurements(measData.measurements ?? []);

      if (valRes.ok) {
        const valData = (await valRes.json()) as SemanticValidation;
        const all = [...(valData.errors ?? []), ...(valData.warnings ?? []), ...(valData.issues ?? [])];
        setProvIssues(all.filter(isProvenanceIssue));
      }
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Failed to load provenance data');
    } finally {
      setLoading(false);
    }
  }, [id]);

  useEffect(() => {
    void load();
  }, [load]);

  const missingProvenance = measurements.filter((m) => !m.raw_file);

  return (
    <div className="p-8 max-w-6xl">
      <div className="mb-6">
        <h1 className="text-2xl font-bold text-slate-900">Provenance</h1>
        <p className="text-sm text-slate-500 mt-1">
          Raw-to-processed lineage for endpoint measurements (read-only).
        </p>
        <p className="text-xs text-amber-700 mt-2">
          POC standardization — requires qualified human review. Not an official submission standard.
        </p>
      </div>

      {loading ? (
        <p className="text-sm text-slate-500">Loading provenance…</p>
      ) : error ? (
        <p className="text-sm text-rose-700">{error}</p>
      ) : (
        <>
          {missingProvenance.length > 0 ? (
            <div className="p-3 mb-4 rounded-lg border border-amber-200 bg-amber-50 text-amber-800 text-sm">
              {missingProvenance.length} measurement(s) are missing raw-file provenance.
            </div>
          ) : null}

          {/* Lineage table */}
          <div className="rounded-xl border border-slate-200 bg-white p-5 mb-6 overflow-x-auto">
            <h2 className="text-sm font-semibold text-slate-800 mb-3">Lineage</h2>
            {measurements.length === 0 ? (
              <p className="text-sm text-slate-500">No measurements to show.</p>
            ) : (
              <table className="w-full text-xs border border-slate-200">
                <thead className="bg-slate-50">
                  <tr>
                    <th className="px-2 py-1.5 text-left font-medium text-slate-600">Endpoint</th>
                    <th className="px-2 py-1.5 text-left font-medium text-slate-600">Lineage</th>
                    <th className="px-2 py-1.5 text-left font-medium text-slate-600">Status</th>
                  </tr>
                </thead>
                <tbody>
                  {measurements.map((m) => (
                    <tr key={m.id} className="odd:bg-white even:bg-slate-50">
                      <td className="px-2 py-1.5 text-slate-700">{m.endpoint_label || m.endpoint_id}</td>
                      <td className="px-2 py-1.5 text-slate-700 font-mono">
                        {(m.endpoint_label || m.endpoint_id)}
                        {' → '}
                        {m.assay ? `${m.assay} → ` : ''}
                        {m.raw_file ? m.raw_file : 'processed value'}
                      </td>
                      <td className="px-2 py-1.5">
                        {m.raw_file ? (
                          <span className="badge bg-green-100 text-green-700 text-xs">traced</span>
                        ) : (
                          <span className="badge bg-amber-100 text-amber-700 text-xs">missing provenance</span>
                        )}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}
          </div>

          {/* Provenance-category validation issues */}
          <div className="rounded-xl border border-slate-200 bg-white p-5">
            <h2 className="text-sm font-semibold text-slate-800 mb-3">Provenance validation issues</h2>
            {provIssues.length === 0 ? (
              <p className="text-sm text-slate-500">No provenance-related validation issues.</p>
            ) : (
              <table className="w-full text-xs border border-slate-200">
                <thead className="bg-slate-50">
                  <tr>
                    <th className="px-2 py-1.5 text-left font-medium text-slate-600">Rule</th>
                    <th className="px-2 py-1.5 text-left font-medium text-slate-600">Entity</th>
                    <th className="px-2 py-1.5 text-left font-medium text-slate-600">Message</th>
                    <th className="px-2 py-1.5 text-left font-medium text-slate-600">Recommended fix</th>
                  </tr>
                </thead>
                <tbody>
                  {provIssues.map((issue, i) => (
                    <tr key={i} className="odd:bg-white even:bg-slate-50">
                      <td className="px-2 py-1.5 text-slate-700 font-mono">{issue.rule}</td>
                      <td className="px-2 py-1.5 text-slate-700">{issue.entity}</td>
                      <td className="px-2 py-1.5 text-slate-700">{issue.message}</td>
                      <td className="px-2 py-1.5 text-slate-600">{issue.recommended_fix}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}
          </div>
        </>
      )}
    </div>
  );
}
