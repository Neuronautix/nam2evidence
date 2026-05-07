'use client';

import { useParams } from 'next/navigation';
import { useStore } from '@/lib/store';
import { useState, useRef, ChangeEvent } from 'react';
import Link from 'next/link';
import { Upload, FileText, CheckCircle2, AlertTriangle, Eye } from 'lucide-react';
import yaml from 'js-yaml';
import { NAMStudy } from '@/lib/types';

type Parsed = Record<string, unknown>;

interface ParseResult {
  ok: boolean;
  data?: Parsed;
  format?: 'json' | 'yaml';
  error?: string;
}

function parseInput(text: string): ParseResult {
  const trimmed = text.trim();
  if (!trimmed) return { ok: false, error: 'Input is empty.' };

  // Try JSON first
  try {
    const data = JSON.parse(trimmed);
    if (typeof data !== 'object' || data === null || Array.isArray(data)) {
      return { ok: false, error: 'Top-level value must be an object.' };
    }
    return { ok: true, data: data as Parsed, format: 'json' };
  } catch {
    // fall through to YAML
  }

  try {
    const data = yaml.load(trimmed);
    if (typeof data !== 'object' || data === null || Array.isArray(data)) {
      return { ok: false, error: 'Top-level value must be a mapping/object.' };
    }
    return { ok: true, data: data as Parsed, format: 'yaml' };
  } catch (e) {
    const msg = e instanceof Error ? e.message : 'Unknown parse error.';
    return { ok: false, error: `YAML/JSON parse failed: ${msg}` };
  }
}

function summarise(value: unknown, depth = 0): string {
  if (value === null || value === undefined) return '—';
  if (typeof value === 'string') return value.length > 120 ? `${value.slice(0, 120)}…` : value;
  if (typeof value === 'number' || typeof value === 'boolean') return String(value);
  if (Array.isArray(value)) {
    return `[${value.length} item${value.length === 1 ? '' : 's'}]`;
  }
  if (typeof value === 'object') {
    if (depth >= 1) return `{${Object.keys(value as object).length} keys}`;
    const entries = Object.entries(value as Record<string, unknown>);
    return `{${entries.length} keys}`;
  }
  return String(value);
}

export default function ImportNAMOPage() {
  const { id } = useParams<{ id: string }>();
  const { getProject, importNAMOStudy } = useStore();
  const project = getProject(id);

  const [text, setText] = useState('');
  const [parseResult, setParseResult] = useState<ParseResult | null>(null);
  const [imported, setImported] = useState<NAMStudy | null>(null);
  const fileInputRef = useRef<HTMLInputElement>(null);

  if (!project) {
    return <div className="p-8 text-slate-500">Project not found.</div>;
  }

  function handleTextChange(e: ChangeEvent<HTMLTextAreaElement>) {
    setText(e.target.value);
    setImported(null);
    if (e.target.value.trim()) {
      setParseResult(parseInput(e.target.value));
    } else {
      setParseResult(null);
    }
  }

  function handleFileChange(e: ChangeEvent<HTMLInputElement>) {
    const file = e.target.files?.[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = () => {
      const content = String(reader.result ?? '');
      setText(content);
      setImported(null);
      setParseResult(parseInput(content));
    };
    reader.onerror = () => {
      setParseResult({ ok: false, error: 'Failed to read file.' });
    };
    reader.readAsText(file);
  }

  function validate(parsed: Parsed): string | null {
    const hasId =
      typeof parsed.id === 'string' ||
      typeof parsed.study_id === 'string';
    const hasName =
      typeof parsed.name === 'string' ||
      typeof parsed.study_name === 'string' ||
      typeof parsed.title === 'string';
    if (!hasId && !hasName) {
      return 'Parsed object must include at least an `id`/`study_id` or `name`/`study_name`/`title` field.';
    }
    return null;
  }

  function handleConfirm() {
    if (!parseResult?.ok || !parseResult.data) return;
    const validationError = validate(parseResult.data);
    if (validationError) {
      setParseResult({ ...parseResult, ok: false, error: validationError });
      return;
    }
    const study = importNAMOStudy(id, parseResult.data);
    setImported(study);
  }

  // Mapped preview (NAMO → NAMStudy)
  const mappedPreview =
    parseResult?.ok && parseResult.data
      ? (() => {
          const p = parseResult.data;
          return [
            ['study_id', String(p.id ?? p.study_id ?? '— (auto-generated)')],
            ['study_name', String(p.name ?? p.study_name ?? p.title ?? 'Imported NAM Study')],
            ['model_system', summarise(p.model_system)],
            ['experimental_design', summarise(p.experimental_design)],
            ['assay_metadata', summarise(p.assay_metadata)],
            ['data_outputs', summarise(p.data_outputs)],
            ['provenance', summarise(p.provenance)],
            ['references', summarise(p.references)],
          ] as const;
        })()
      : null;

  return (
    <div className="p-8 max-w-4xl">
      <div className="mb-6">
        <h1 className="text-2xl font-bold text-slate-900">Import NAMO Study</h1>
        <p className="text-sm text-slate-500 mt-1">
          Paste NAMO-aligned YAML or JSON, or upload a file. The parsed payload will be mapped to
          the project&apos;s NAM Study record.
        </p>
      </div>

      {/* Source input */}
      <div className="card p-5 mb-5">
        <div className="flex items-center justify-between mb-3">
          <p className="label !mb-0">Source (YAML or JSON)</p>
          <div className="flex gap-2">
            <input
              ref={fileInputRef}
              type="file"
              accept=".yaml,.yml,.json,application/json,text/yaml"
              onChange={handleFileChange}
              className="hidden"
            />
            <button
              type="button"
              className="btn-secondary"
              onClick={() => fileInputRef.current?.click()}
            >
              <Upload className="w-4 h-4" />
              Upload file
            </button>
          </div>
        </div>
        <textarea
          rows={14}
          value={text}
          onChange={handleTextChange}
          placeholder={`# Paste NAMO YAML or JSON here\nid: NAM-STUDY-002\nname: Example Organoid Study\nmodel_system:\n  namo_class: Organoid\n  species: Homo sapiens`}
          className="w-full border border-slate-300 rounded-lg px-3 py-2 text-xs font-mono focus:outline-none focus:ring-2 focus:ring-blue-500 resize-y"
          spellCheck={false}
        />
        {parseResult && !parseResult.ok && (
          <div className="mt-3 flex items-start gap-2 p-3 rounded-lg bg-red-50 border border-red-200 text-xs text-red-800">
            <AlertTriangle className="w-4 h-4 flex-shrink-0 mt-0.5" />
            <span>{parseResult.error}</span>
          </div>
        )}
        {parseResult?.ok && (
          <div className="mt-3 flex items-center gap-2 text-xs text-green-700">
            <CheckCircle2 className="w-4 h-4" />
            Parsed successfully as {parseResult.format?.toUpperCase()}.
          </div>
        )}
      </div>

      {/* Preview */}
      {mappedPreview && parseResult?.ok && (
        <div className="card p-5 mb-5">
          <div className="flex items-center gap-2 mb-3">
            <Eye className="w-4 h-4 text-slate-500" />
            <p className="label !mb-0">Mapped Preview (NAMO → NAMStudy)</p>
          </div>
          <table className="w-full text-xs">
            <tbody>
              {mappedPreview.map(([k, v]) => (
                <tr key={k} className="border-b border-slate-100 last:border-0">
                  <td className="py-2 pr-3 font-mono text-slate-500 align-top w-44">{k}</td>
                  <td className="py-2 text-slate-800 break-words">{v}</td>
                </tr>
              ))}
            </tbody>
          </table>
          <div className="mt-4 flex justify-end gap-2">
            <button
              type="button"
              className="btn-secondary"
              onClick={() => {
                setText('');
                setParseResult(null);
                setImported(null);
                if (fileInputRef.current) fileInputRef.current.value = '';
              }}
            >
              Clear
            </button>
            <button type="button" className="btn-primary" onClick={handleConfirm}>
              <CheckCircle2 className="w-4 h-4" />
              Confirm import
            </button>
          </div>
        </div>
      )}

      {/* Success */}
      {imported && (
        <div
          role="status"
          className="card p-5 border-2 border-green-300 bg-green-50"
        >
          <div className="flex items-start gap-3">
            <CheckCircle2 className="w-5 h-5 text-green-700 mt-0.5 flex-shrink-0" />
            <div>
              <p className="font-semibold text-green-900">Study imported successfully</p>
              <p className="text-sm text-green-800 mt-1">
                {imported.title} ({imported.study_id}) has been saved to this project.
              </p>
              <Link
                href={`/projects/${id}/study`}
                className="inline-flex items-center gap-1.5 mt-3 text-sm text-blue-700 hover:underline font-medium"
              >
                <FileText className="w-4 h-4" />
                Open Study page
              </Link>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
