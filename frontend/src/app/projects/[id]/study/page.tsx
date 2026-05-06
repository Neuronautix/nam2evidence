'use client';

import { useParams } from 'next/navigation';
import { useStore } from '@/lib/store';
import Link from 'next/link';

export default function StudyPage() {
  const { id } = useParams<{ id: string }>();
  const { getStudy, getCOU } = useStore();
  const study = getStudy(id);
  const cou = getCOU(id);

  if (!study) {
    return <div className="p-8 text-slate-500">No NAM study metadata found for this project.</div>;
  }

  const ms = study.model_system;

  return (
    <div className="p-8 max-w-4xl">
      <div className="mb-6">
        <h1 className="text-2xl font-bold text-slate-900">NAM Study Metadata</h1>
        <p className="text-sm text-slate-500 mt-1">
          {study.study_id} · NAMO-aligned metadata record
        </p>
      </div>

      <div className="space-y-5">
        {/* Study header */}
        <div className="card p-5">
          <p className="label">Study Title</p>
          <p className="text-base font-semibold text-slate-900">{study.title}</p>
          <div className="flex items-center gap-4 mt-2 text-sm text-slate-500">
            <span>Study ID: <span className="font-mono text-slate-700">{study.study_id}</span></span>
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

        {/* Experimental Design */}
        <div className="card p-5">
          <p className="label">Experimental Design</p>
          <div className="grid grid-cols-2 gap-x-8 gap-y-3 mt-2">
            {Object.entries(study.experimental_design).map(([key, val]) => (
              <div key={key} className="col-span-1">
                <p className="label">{key.replace(/_/g, ' ')}</p>
                <p className="value text-xs">
                  {Array.isArray(val)
                    ? val.map((v, i) =>
                        typeof v === 'object' ? (
                          <span key={i} className="block">
                            {(v as { name: string }).name} ({(v as { class: string; expected: string }).class} · {(v as { expected: string }).expected})
                          </span>
                        ) : (
                          <span key={i}>{String(v)}{i < (val as unknown[]).length - 1 ? ', ' : ''}</span>
                        )
                      )
                    : String(val)}
                </p>
              </div>
            ))}
          </div>
        </div>

        {/* Assay Metadata */}
        <div className="card p-5">
          <p className="label">Assay Metadata</p>
          {(study.assay_metadata.primary_endpoints as Array<{ name: string; method: string; readout: string; unit: string }>)?.length > 0 && (
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
                  {(study.assay_metadata.primary_endpoints as Array<{ name: string; method: string; readout: string; unit: string }>).map((ep, i) => (
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
              <p className="value">{String(study.assay_metadata.instrument)}</p>
            </div>
            <div>
              <p className="label">Software</p>
              <p className="value">{String(study.assay_metadata.software)}</p>
            </div>
          </div>
        </div>

        {/* Data Outputs */}
        <div className="card p-5">
          <p className="label">Key Data Outputs</p>
          <div className="grid grid-cols-3 gap-4 mt-2">
            {[
              ['TC₅₀ (ATP)', `${(study.data_outputs.tc50_atp_uM as { value: number }).value} µM`],
              ['TC₅₀ (LDH)', `${(study.data_outputs.tc50_ldh_uM as { value: number }).value} µM`],
              ['NOAEL', `${String(study.data_outputs.noael_uM)} µM`],
              ['Human Cmax', `${String(study.data_outputs.human_cmax_uM)} µM`],
              ['Safety Multiple (NOAEL)', `${String(study.data_outputs.safety_multiple_noael)}×`],
              ['Safety Multiple (TC₅₀)', `${String(study.data_outputs.safety_multiple_tc50)}×`],
              ['Bile Acid Increase', `${String(study.data_outputs.bile_acid_increase_fold)}×`],
              ['MMP Decrease at 10 µM', `${String(study.data_outputs.mmp_decrease_pct_at_10uM)}%`],
            ].map(([key, val]) => (
              <div key={key} className="bg-slate-50 rounded-lg p-3">
                <p className="text-xs text-slate-500">{key}</p>
                <p className="text-lg font-bold text-slate-900">{val}</p>
              </div>
            ))}
          </div>
        </div>

        {/* Provenance */}
        <div className="card p-5">
          <p className="label">Provenance & Data Integrity</p>
          <div className="grid grid-cols-2 gap-x-8 gap-y-3 mt-2">
            {Object.entries(study.provenance).map(([key, val]) => (
              <div key={key}>
                <p className="label">{key.replace(/_/g, ' ')}</p>
                <p className="value text-xs break-all">{Array.isArray(val) ? val.join(', ') : String(val)}</p>
              </div>
            ))}
          </div>
        </div>
      </div>
    </div>
  );
}
