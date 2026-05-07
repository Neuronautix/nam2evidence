'use client';

import { useParams } from 'next/navigation';
import { useStore } from '@/lib/store';
import Link from 'next/link';
import { useState } from 'react';
import { Save, Edit2, AlertTriangle } from 'lucide-react';
import { NAMStudy } from '@/lib/types';

interface JsonFieldState {
  text: string;
  error: string | null;
}

const JSON_FIELDS = [
  'experimental_design',
  'assay_metadata',
  'data_outputs',
  'provenance',
] as const;

type JsonFieldKey = (typeof JSON_FIELDS)[number];

export default function StudyPage() {
  const { id } = useParams<{ id: string }>();
  const { getStudy, getCOU, updateStudy } = useStore();
  const study = getStudy(id);
  const cou = getCOU(id);

  const [editing, setEditing] = useState(false);
  const [draftTitle, setDraftTitle] = useState('');
  const [jsonFields, setJsonFields] = useState<Record<JsonFieldKey, JsonFieldState>>({
    experimental_design: { text: '', error: null },
    assay_metadata: { text: '', error: null },
    data_outputs: { text: '', error: null },
    provenance: { text: '', error: null },
  });

  if (!study) {
    return (
      <div className="p-8 text-slate-500">
        No NAM study metadata found for this project.{' '}
        <Link href={`/projects/${id}/import`} className="text-blue-600 underline">
          Import one
        </Link>
        .
      </div>
    );
  }

  const ms = study.model_system;

  function startEdit() {
    if (!study) return;
    setDraftTitle(study.title);
    setJsonFields({
      experimental_design: {
        text: JSON.stringify(study.experimental_design, null, 2),
        error: null,
      },
      assay_metadata: {
        text: JSON.stringify(study.assay_metadata, null, 2),
        error: null,
      },
      data_outputs: {
        text: JSON.stringify(study.data_outputs, null, 2),
        error: null,
      },
      provenance: {
        text: JSON.stringify(study.provenance, null, 2),
        error: null,
      },
    });
    setEditing(true);
  }

  function cancel() {
    setEditing(false);
  }

  function updateJsonField(key: JsonFieldKey, text: string) {
    let error: string | null = null;
    try {
      const parsed = JSON.parse(text);
      if (typeof parsed !== 'object' || parsed === null || Array.isArray(parsed)) {
        error = 'Top-level value must be an object.';
      }
    } catch (e) {
      error = e instanceof Error ? e.message : 'Invalid JSON';
    }
    setJsonFields((prev) => ({ ...prev, [key]: { text, error } }));
  }

  function save() {
    if (!study) return;
    const partial: Partial<NAMStudy> = { title: draftTitle };
    for (const key of JSON_FIELDS) {
      const f = jsonFields[key];
      if (f.error) return; // block save with errors
      try {
        partial[key] = JSON.parse(f.text) as Record<string, unknown>;
      } catch {
        return;
      }
    }
    updateStudy(study.study_id, partial);
    setEditing(false);
  }

  const hasJsonError = Object.values(jsonFields).some((f) => f.error !== null);

  return (
    <div className="p-8 max-w-4xl">
      <div className="flex items-center justify-between mb-6">
        <div>
          <h1 className="text-2xl font-bold text-slate-900">NAM Study Metadata</h1>
          <p className="text-sm text-slate-500 mt-1">
            {study.study_id} · NAMO-aligned metadata record
          </p>
        </div>
        <div className="flex gap-2">
          {editing ? (
            <>
              <button className="btn-secondary" onClick={cancel}>
                Cancel
              </button>
              <button
                className="btn-primary"
                onClick={save}
                disabled={hasJsonError}
                title={hasJsonError ? 'Fix JSON errors before saving' : undefined}
              >
                <Save className="w-4 h-4" />
                Save
              </button>
            </>
          ) : (
            <button className="btn-secondary" onClick={startEdit}>
              <Edit2 className="w-4 h-4" />
              Edit
            </button>
          )}
        </div>
      </div>

      <div className="space-y-5">
        {/* Study header */}
        <div className="card p-5">
          <p className="label">Study Title</p>
          {editing ? (
            <input
              type="text"
              className="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
              value={draftTitle}
              onChange={(e) => setDraftTitle(e.target.value)}
            />
          ) : (
            <p className="text-base font-semibold text-slate-900">{study.title}</p>
          )}
          <div className="flex items-center gap-4 mt-2 text-sm text-slate-500">
            <span>
              Study ID: <span className="font-mono text-slate-700">{study.study_id}</span>
            </span>
            {cou && (
              <span>
                COU:{' '}
                <Link href={`/projects/${id}/cou`} className="text-blue-600 hover:underline">
                  {cou.cou_id}
                </Link>
              </span>
            )}
            <span>Created: {new Date(study.created_at).toLocaleDateString()}</span>
          </div>
        </div>

        {/* Model System */}
        <div className="card p-5">
          <p className="label">Model System (NAMO Classification)</p>
          <div className="grid grid-cols-2 gap-x-8 gap-y-3 mt-2">
            {[
              ['NAMO Class', ms.namo_class],
              ['Species', ms.species],
              ['Cell Type', ms.cell_type],
              ['Tissue Origin', ms.tissue_origin],
              ['Culture Conditions', ms.culture_conditions],
              ['Vendor / Source', ms.vendor],
              ['Catalog #', ms.catalog_number ?? '—'],
              ['Passage', ms.passage_number ?? '—'],
            ].map(([key, val]) => (
              <div key={key}>
                <p className="label">{key}</p>
                <p className="value">{val}</p>
              </div>
            ))}
          </div>
          {ms.maturity_indicators && ms.maturity_indicators.length > 0 && (
            <div className="mt-4">
              <p className="label">Maturity / Quality Indicators</p>
              <ul className="space-y-1 mt-1">
                {ms.maturity_indicators.map((m, i) => (
                  <li key={i} className="flex items-center gap-2 text-sm text-slate-700">
                    <span className="w-1.5 h-1.5 rounded-full bg-teal-500 flex-shrink-0" />
                    {m}
                  </li>
                ))}
              </ul>
            </div>
          )}
        </div>

        {/* JSON-editable sections */}
        {JSON_FIELDS.map((key) => (
          <JsonSection
            key={key}
            title={key.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase())}
            value={study[key]}
            editing={editing}
            fieldState={jsonFields[key]}
            onChange={(text) => updateJsonField(key, text)}
            renderReadOnly={() => renderReadOnlyForKey(study, key)}
          />
        ))}
      </div>
    </div>
  );
}

function JsonSection({
  title,
  editing,
  fieldState,
  onChange,
  renderReadOnly,
}: {
  title: string;
  value: Record<string, unknown>;
  editing: boolean;
  fieldState: JsonFieldState;
  onChange: (text: string) => void;
  renderReadOnly: () => React.ReactNode;
}) {
  return (
    <div className="card p-5">
      <p className="label">{title}</p>
      {editing ? (
        <div>
          <textarea
            rows={10}
            value={fieldState.text}
            onChange={(e) => onChange(e.target.value)}
            className="w-full border border-slate-300 rounded-lg px-3 py-2 text-xs font-mono focus:outline-none focus:ring-2 focus:ring-blue-500 resize-y"
            spellCheck={false}
          />
          {fieldState.error && (
            <div className="mt-2 flex items-start gap-2 p-2 rounded-lg bg-red-50 border border-red-200 text-xs text-red-800">
              <AlertTriangle className="w-3.5 h-3.5 flex-shrink-0 mt-0.5" />
              <span>{fieldState.error}</span>
            </div>
          )}
        </div>
      ) : (
        renderReadOnly()
      )}
    </div>
  );
}

function renderReadOnlyForKey(study: NAMStudy, key: JsonFieldKey): React.ReactNode {
  const value = study[key];

  if (key === 'assay_metadata') {
    const eps = (study.assay_metadata.primary_endpoints as
      | Array<{ name: string; method: string; readout: string; unit: string }>
      | undefined) ?? [];
    return (
      <>
        {eps.length > 0 && (
          <div className="mt-2 overflow-x-auto">
            <table className="w-full text-xs">
              <thead>
                <tr className="bg-slate-50 border-b border-slate-200">
                  <th className="text-left px-3 py-2 text-slate-500 font-medium">Endpoint</th>
                  <th className="text-left px-3 py-2 text-slate-500 font-medium">Method</th>
                  <th className="text-left px-3 py-2 text-slate-500 font-medium">Readout</th>
                  <th className="text-left px-3 py-2 text-slate-500 font-medium">Unit</th>
                </tr>
              </thead>
              <tbody>
                {eps.map((ep, i) => (
                  <tr key={i} className={i % 2 === 0 ? 'bg-white' : 'bg-slate-50'}>
                    <td className="px-3 py-2 font-medium text-slate-700">{ep.name}</td>
                    <td className="px-3 py-2 text-slate-600">{ep.method}</td>
                    <td className="px-3 py-2 text-slate-600">{ep.readout}</td>
                    <td className="px-3 py-2 text-slate-600">{ep.unit}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
        <div className="mt-3 grid grid-cols-2 gap-4 text-xs">
          <div>
            <p className="label">Instrument</p>
            <p className="value">{String(study.assay_metadata.instrument ?? '—')}</p>
          </div>
          <div>
            <p className="label">Software</p>
            <p className="value">{String(study.assay_metadata.software ?? '—')}</p>
          </div>
        </div>
      </>
    );
  }

  if (key === 'data_outputs') {
    const out = study.data_outputs;
    const tc50_atp =
      (out.tc50_atp_uM as { value?: number } | undefined)?.value ?? out.tc50_atp_uM;
    const tc50_ldh =
      (out.tc50_ldh_uM as { value?: number } | undefined)?.value ?? out.tc50_ldh_uM;
    const tiles: Array<[string, string]> = [
      ['TC50 (ATP)', tc50_atp !== undefined ? `${String(tc50_atp)} µM` : '—'],
      ['TC50 (LDH)', tc50_ldh !== undefined ? `${String(tc50_ldh)} µM` : '—'],
      ['NOAEL', out.noael_uM !== undefined ? `${String(out.noael_uM)} µM` : '—'],
      ['Human Cmax', out.human_cmax_uM !== undefined ? `${String(out.human_cmax_uM)} µM` : '—'],
      [
        'Safety Multiple (NOAEL)',
        out.safety_multiple_noael !== undefined ? `${String(out.safety_multiple_noael)}x` : '—',
      ],
      [
        'Safety Multiple (TC50)',
        out.safety_multiple_tc50 !== undefined ? `${String(out.safety_multiple_tc50)}x` : '—',
      ],
      [
        'Bile Acid Increase',
        out.bile_acid_increase_fold !== undefined
          ? `${String(out.bile_acid_increase_fold)}x`
          : '—',
      ],
      [
        'MMP Decrease at 10 µM',
        out.mmp_decrease_pct_at_10uM !== undefined
          ? `${String(out.mmp_decrease_pct_at_10uM)}%`
          : '—',
      ],
    ];
    return (
      <div className="grid grid-cols-3 gap-4 mt-2">
        {tiles.map(([k, v]) => (
          <div key={k} className="bg-slate-50 rounded-lg p-3">
            <p className="text-xs text-slate-500">{k}</p>
            <p className="text-lg font-bold text-slate-900">{v}</p>
          </div>
        ))}
      </div>
    );
  }

  // Default: key-value grid (used for experimental_design and provenance)
  const entries = Object.entries(value);
  return (
    <div className="grid grid-cols-2 gap-x-8 gap-y-3 mt-2">
      {entries.map(([k, v]) => (
        <div key={k} className="col-span-1">
          <p className="label">{k.replace(/_/g, ' ')}</p>
          <p className="value text-xs break-all">
            {Array.isArray(v)
              ? v
                  .map((item) =>
                    typeof item === 'object' && item !== null
                      ? JSON.stringify(item)
                      : String(item)
                  )
                  .join(', ')
              : typeof v === 'object' && v !== null
              ? JSON.stringify(v)
              : String(v)}
          </p>
        </div>
      ))}
    </div>
  );
}
