'use client';

import { useCallback, useEffect, useState } from 'react';
import { useParams } from 'next/navigation';

const API = process.env.NEXT_PUBLIC_API_URL ?? 'http://localhost:8080';

interface ImportPreview {
  columns: string[];
  suggested_mapping: Record<string, string>;
  sample_rows: Array<Record<string, string>>;
  row_count: number;
}

interface PreviewResponse {
  mode: 'preview';
  preview: ImportPreview;
  target_fields: string[];
}

interface ImportIssue {
  row: number;
  field: string;
  message: string;
}

interface UnitNormalization {
  row: number;
  from: string;
  to: string;
}

interface ImportSummary {
  imported: number;
  total_rows: number;
  error_count: number;
  warning_count: number;
  errors: ImportIssue[];
  warnings: ImportIssue[];
  unit_normalizations: UnitNormalization[];
  blocking: boolean;
}

interface ImportResponse {
  mode: 'import';
  summary: ImportSummary;
}

interface Measurement {
  id: string;
  endpoint_id: string;
  endpoint_label: string;
  value: string | number | null;
  value_raw: string | null;
  unit: string | null;
  unit_iri: string | null;
  timepoint_value: string | number | null;
  timepoint_unit: string | null;
  replicate_id: string | null;
  batch_id: string | null;
  qc_status: string | null;
  exclusion_status: string | null;
  validation_status: string | null;
  study: string | null;
  sample: string | null;
  assay: string | null;
  exposure: string | null;
  raw_file: string | null;
  unresolved: boolean;
}

interface MeasurementsResponse {
  count: number;
  measurements: Measurement[];
}

function validationBadgeClass(status: string | null): string {
  switch ((status ?? '').toLowerCase()) {
    case 'valid':
    case 'passed':
    case 'conforms':
      return 'bg-green-100 text-green-700';
    case 'warning':
      return 'bg-amber-100 text-amber-700';
    case 'error':
    case 'invalid':
    case 'failed':
      return 'bg-rose-100 text-rose-700';
    default:
      return 'bg-slate-100 text-slate-600';
  }
}

export default function EndpointStandardizationPage() {
  const { id } = useParams<{ id: string }>();

  const [csv, setCsv] = useState('');
  const [preview, setPreview] = useState<PreviewResponse | null>(null);
  const [mapping, setMapping] = useState<Record<string, string>>({});
  const [importSummary, setImportSummary] = useState<ImportSummary | null>(null);
  const [busy, setBusy] = useState<'preview' | 'import' | null>(null);
  const [error, setError] = useState<string | null>(null);

  const [measurements, setMeasurements] = useState<Measurement[]>([]);
  const [measLoading, setMeasLoading] = useState(true);
  const [measError, setMeasError] = useState<string | null>(null);

  const loadMeasurements = useCallback(async () => {
    setMeasLoading(true);
    setMeasError(null);
    try {
      const res = await fetch(`${API}/api/v1/projects/${id}/endpoint-measurements`, {
        cache: 'no-store',
      });
      if (!res.ok) throw new Error(`API ${res.status}`);
      const data = (await res.json()) as MeasurementsResponse;
      setMeasurements(data.measurements ?? []);
    } catch (e) {
      setMeasError(e instanceof Error ? e.message : 'Failed to load measurements');
    } finally {
      setMeasLoading(false);
    }
  }, [id]);

  useEffect(() => {
    void loadMeasurements();
  }, [loadMeasurements]);

  const handleFile = (file: File | undefined) => {
    if (!file) return;
    const reader = new FileReader();
    reader.onload = () => {
      setCsv(typeof reader.result === 'string' ? reader.result : '');
    };
    reader.readAsText(file);
  };

  const handlePreview = async () => {
    setBusy('preview');
    setError(null);
    setImportSummary(null);
    try {
      const res = await fetch(`${API}/api/v1/projects/${id}/endpoint-measurements/import`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ csv }),
      });
      if (!res.ok) throw new Error(`API ${res.status}: ${await res.text()}`);
      const data = (await res.json()) as PreviewResponse;
      setPreview(data);
      setMapping({ ...data.preview.suggested_mapping });
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Preview failed');
    } finally {
      setBusy(null);
    }
  };

  const handleImport = async () => {
    setBusy('import');
    setError(null);
    try {
      const res = await fetch(`${API}/api/v1/projects/${id}/endpoint-measurements/import`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ csv, mapping }),
      });
      if (!res.ok) throw new Error(`API ${res.status}: ${await res.text()}`);
      const data = (await res.json()) as ImportResponse;
      setImportSummary(data.summary);
      await loadMeasurements();
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Import failed');
    } finally {
      setBusy(null);
    }
  };

  return (
    <div className="p-8 max-w-6xl">
      <div className="mb-6">
        <h1 className="text-2xl font-bold text-slate-900">Endpoint Data Standardization</h1>
        <p className="text-sm text-slate-500 mt-1">
          Import endpoint measurement CSVs and map columns to NAM-CORE target fields.
        </p>
        <p className="text-xs text-amber-700 mt-2">
          POC standardization — requires qualified human review. Not an official submission standard.
        </p>
      </div>

      {error ? (
        <div className="p-3 mb-4 rounded-lg border border-rose-200 bg-rose-50 text-rose-700 text-sm">
          {error}
        </div>
      ) : null}

      {/* CSV input */}
      <div className="rounded-xl border border-slate-200 bg-white p-5 mb-6">
        <h2 className="text-sm font-semibold text-slate-800 mb-3">1. Paste or upload CSV</h2>
        <textarea
          className="w-full h-40 rounded-lg border border-slate-300 p-3 text-sm font-mono"
          placeholder="endpoint,value,unit,timepoint..."
          value={csv}
          onChange={(e) => setCsv(e.target.value)}
        />
        <div className="flex items-center gap-3 mt-3">
          <input
            type="file"
            accept=".csv,text/csv"
            className="text-xs text-slate-600"
            onChange={(e) => handleFile(e.target.files?.[0])}
          />
          <button
            className="btn-primary text-xs py-1.5"
            onClick={() => void handlePreview()}
            disabled={!csv.trim() || busy !== null}
          >
            {busy === 'preview' ? 'Previewing...' : 'Preview'}
          </button>
        </div>
      </div>

      {/* Mapping + preview */}
      {preview ? (
        <div className="rounded-xl border border-slate-200 bg-white p-5 mb-6">
          <h2 className="text-sm font-semibold text-slate-800 mb-1">2. Map columns</h2>
          <p className="text-xs text-slate-500 mb-4">
            {preview.preview.row_count} row(s) detected. Adjust the target field for each column.
          </p>
          <div className="grid grid-cols-2 gap-3 mb-5">
            {preview.preview.columns.map((col) => (
              <div key={col} className="flex items-center gap-2">
                <span className="text-xs font-mono text-slate-700 w-1/2 truncate" title={col}>
                  {col}
                </span>
                <select
                  className="flex-1 rounded-lg border border-slate-300 p-1.5 text-xs"
                  value={mapping[col] ?? ''}
                  onChange={(e) =>
                    setMapping((prev) => ({ ...prev, [col]: e.target.value }))
                  }
                >
                  <option value="">— ignore —</option>
                  {preview.target_fields.map((f) => (
                    <option key={f} value={f}>
                      {f}
                    </option>
                  ))}
                </select>
              </div>
            ))}
          </div>

          <h3 className="text-xs font-semibold text-slate-600 mb-2">Sample rows</h3>
          <div className="overflow-x-auto">
            <table className="w-full text-xs border border-slate-200">
              <thead className="bg-slate-50">
                <tr>
                  {preview.preview.columns.map((col) => (
                    <th key={col} className="px-2 py-1.5 text-left font-medium text-slate-600 border-b border-slate-200">
                      {col}
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {preview.preview.sample_rows.map((row, i) => (
                  <tr key={i} className="odd:bg-white even:bg-slate-50">
                    {preview.preview.columns.map((col) => (
                      <td key={col} className="px-2 py-1.5 text-slate-700 border-b border-slate-100">
                        {row[col] ?? ''}
                      </td>
                    ))}
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          <button
            className="btn-primary text-xs py-1.5 mt-4"
            onClick={() => void handleImport()}
            disabled={busy !== null}
          >
            {busy === 'import' ? 'Importing...' : 'Import'}
          </button>
        </div>
      ) : null}

      {/* Import summary */}
      {importSummary ? (
        <div className="rounded-xl border border-slate-200 bg-white p-5 mb-6">
          <h2 className="text-sm font-semibold text-slate-800 mb-3">3. Import summary</h2>
          <div className="flex flex-wrap gap-4 text-sm mb-4">
            <span className="text-slate-700">
              Imported <strong>{importSummary.imported}</strong> / {importSummary.total_rows}
            </span>
            <span className="text-rose-700">Errors: {importSummary.error_count}</span>
            <span className="text-amber-700">Warnings: {importSummary.warning_count}</span>
            {importSummary.blocking ? (
              <span className="badge bg-rose-100 text-rose-700 text-xs">Blocking</span>
            ) : null}
          </div>

          {importSummary.errors.length > 0 ? (
            <div className="mb-4">
              <h3 className="text-xs font-semibold text-rose-700 mb-1">Errors</h3>
              <IssueTable issues={importSummary.errors} />
            </div>
          ) : null}

          {importSummary.warnings.length > 0 ? (
            <div className="mb-4">
              <h3 className="text-xs font-semibold text-amber-700 mb-1">Warnings</h3>
              <IssueTable issues={importSummary.warnings} />
            </div>
          ) : null}

          {importSummary.unit_normalizations.length > 0 ? (
            <div>
              <h3 className="text-xs font-semibold text-slate-600 mb-1">Unit normalizations</h3>
              <table className="w-full text-xs border border-slate-200">
                <thead className="bg-slate-50">
                  <tr>
                    <th className="px-2 py-1.5 text-left font-medium text-slate-600">Row</th>
                    <th className="px-2 py-1.5 text-left font-medium text-slate-600">From</th>
                    <th className="px-2 py-1.5 text-left font-medium text-slate-600">To</th>
                  </tr>
                </thead>
                <tbody>
                  {importSummary.unit_normalizations.map((u, i) => (
                    <tr key={i} className="odd:bg-white even:bg-slate-50">
                      <td className="px-2 py-1.5 text-slate-700">{u.row}</td>
                      <td className="px-2 py-1.5 text-slate-700 font-mono">{u.from}</td>
                      <td className="px-2 py-1.5 text-slate-700 font-mono">{u.to}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          ) : null}
        </div>
      ) : null}

      {/* Stored measurements */}
      <div className="rounded-xl border border-slate-200 bg-white p-5">
        <h2 className="text-sm font-semibold text-slate-800 mb-3">Stored endpoint measurements</h2>
        {measLoading ? (
          <p className="text-sm text-slate-500">Loading measurements…</p>
        ) : measError ? (
          <p className="text-sm text-rose-700">{measError}</p>
        ) : measurements.length === 0 ? (
          <p className="text-sm text-slate-500">No measurements imported yet.</p>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-xs border border-slate-200">
              <thead className="bg-slate-50">
                <tr>
                  <th className="px-2 py-1.5 text-left font-medium text-slate-600">Endpoint</th>
                  <th className="px-2 py-1.5 text-left font-medium text-slate-600">Value</th>
                  <th className="px-2 py-1.5 text-left font-medium text-slate-600">Unit</th>
                  <th className="px-2 py-1.5 text-left font-medium text-slate-600">Timepoint</th>
                  <th className="px-2 py-1.5 text-left font-medium text-slate-600">QC</th>
                  <th className="px-2 py-1.5 text-left font-medium text-slate-600">Validation</th>
                  <th className="px-2 py-1.5 text-left font-medium text-slate-600">Provenance</th>
                </tr>
              </thead>
              <tbody>
                {measurements.map((m) => (
                  <tr key={m.id} className="odd:bg-white even:bg-slate-50">
                    <td className="px-2 py-1.5 text-slate-700">{m.endpoint_label || m.endpoint_id}</td>
                    <td className="px-2 py-1.5 text-slate-700">
                      {m.value ?? m.value_raw ?? ''}
                    </td>
                    <td className="px-2 py-1.5 text-slate-700">{m.unit ?? ''}</td>
                    <td className="px-2 py-1.5 text-slate-700">
                      {m.timepoint_value != null ? `${m.timepoint_value} ${m.timepoint_unit ?? ''}` : ''}
                    </td>
                    <td className="px-2 py-1.5 text-slate-700">{m.qc_status ?? ''}</td>
                    <td className="px-2 py-1.5">
                      <span className={`badge text-xs ${validationBadgeClass(m.validation_status)}`}>
                        {m.validation_status ?? 'unknown'}
                      </span>
                    </td>
                    <td className="px-2 py-1.5">
                      {m.unresolved ? (
                        <span className="badge bg-amber-100 text-amber-700 text-xs">unresolved</span>
                      ) : (
                        <span className="badge bg-green-100 text-green-700 text-xs">resolved</span>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>
    </div>
  );
}

function IssueTable({ issues }: { issues: ImportIssue[] }) {
  return (
    <table className="w-full text-xs border border-slate-200">
      <thead className="bg-slate-50">
        <tr>
          <th className="px-2 py-1.5 text-left font-medium text-slate-600">Row</th>
          <th className="px-2 py-1.5 text-left font-medium text-slate-600">Field</th>
          <th className="px-2 py-1.5 text-left font-medium text-slate-600">Message</th>
        </tr>
      </thead>
      <tbody>
        {issues.map((issue, i) => (
          <tr key={i} className="odd:bg-white even:bg-slate-50">
            <td className="px-2 py-1.5 text-slate-700">{issue.row}</td>
            <td className="px-2 py-1.5 text-slate-700 font-mono">{issue.field}</td>
            <td className="px-2 py-1.5 text-slate-700">{issue.message}</td>
          </tr>
        ))}
      </tbody>
    </table>
  );
}
