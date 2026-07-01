'use client';

import { useState } from 'react';
import { Project, ContextOfUseCard, NAMStudy, EvidenceItem, ClaimNode, ECTDMapping } from '@/lib/types';
import { Download, FileJson, FileText, FileSpreadsheet, FolderTree, AlertTriangle, CheckCircle, Boxes, Share2, Database, Package } from 'lucide-react';
import {
  ApiDownloadError,
  ExportFormat,
  generateAndDownloadExport,
  saveBlob,
} from '@/lib/api';

const API = process.env.NEXT_PUBLIC_API_URL ?? 'http://localhost:8080';

interface ExportCenterProps {
  project: Project;
  cou: ContextOfUseCard;
  study: NAMStudy;
  evidenceItems: EvidenceItem[];
  claimNodes: ClaimNode[];
  ectdMappings: ECTDMapping[];
}

export default function ExportCenter(props: ExportCenterProps) {
  const { claimNodes, project } = props;
  const [downloadingFormat, setDownloadingFormat] = useState<ExportFormat | null>(null);
  const [apiError, setApiError] = useState<string | null>(null);
  const [backendPendingIds, setBackendPendingIds] = useState<string[]>([]);

  const pendingClaims = claimNodes.filter((c) => c.review_status === 'human_review_required');

  const effectivePendingIds = backendPendingIds.length > 0
    ? backendPendingIds
    : pendingClaims.map((c) => c.claim_id);
  const isBlocked = effectivePendingIds.length > 0;

  const handleDownload = async (format: ExportFormat) => {
    setDownloadingFormat(format);
    setApiError(null);
    setBackendPendingIds([]);

    try {
      const { blob, filename } = await generateAndDownloadExport(project.id, format);
      saveBlob(blob, filename);
    } catch (error) {
      if (error instanceof ApiDownloadError) {
        if (error.status === 422) {
          setBackendPendingIds(error.pendingIds);
          setApiError(error.message);
        } else {
          setApiError(`Export failed (${error.status}): ${error.message}`);
        }
      } else if (error instanceof Error) {
        setApiError(error.message);
      } else {
        setApiError('Export failed due to an unexpected error.');
      }
    } finally {
      setDownloadingFormat(null);
    }
  };

  return (
    <div className="space-y-6">
      {/* Review gate */}
      <div className={`p-4 rounded-xl border ${!isBlocked ? 'bg-green-50 border-green-200' : 'bg-amber-50 border-amber-200'}`}>
        <div className="flex items-center gap-3">
          {!isBlocked ? (
            <CheckCircle className="w-5 h-5 text-green-600 flex-shrink-0" />
          ) : (
            <AlertTriangle className="w-5 h-5 text-amber-600 flex-shrink-0" />
          )}
          <div>
            <p className={`text-sm font-semibold ${!isBlocked ? 'text-green-800' : 'text-amber-800'}`}>
              {!isBlocked ? 'All claims approved - export enabled' : `Human review required for ${effectivePendingIds.length} claim(s)`}
            </p>
            <p className={`text-xs mt-0.5 ${!isBlocked ? 'text-green-700' : 'text-amber-700'}`}>
              {!isBlocked
                ? 'The evidence package is ready for export. Outputs are tool-generated and not a substitute for qualified regulatory review.'
                : `Review and approve all claims in the Claim Graph workspace before exporting. Pending: ${effectivePendingIds.join(', ')}`}
            </p>
          </div>
        </div>
      </div>

      {apiError ? (
        <div className="p-3 rounded-lg border border-rose-200 bg-rose-50 text-rose-700 text-sm">
          {apiError}
        </div>
      ) : null}

      {/* Export actions */}
      <div className="grid grid-cols-2 gap-4">
        {[
          {
            icon: FileJson,
            title: 'JSON Package',
            description: 'Complete machine-readable evidence package (project, COU, study, evidence matrix, claims, eCTD mappings)',
            format: 'json' as ExportFormat,
            label: 'JSON',
            color: 'border-blue-200 hover:border-blue-400',
            iconColor: 'text-blue-600',
          },
          {
            icon: FileSpreadsheet,
            title: 'Validation Evidence Matrix',
            description: 'CSV export of all validation evidence items across all eight domains, suitable for regulatory submission appendix',
            format: 'csv' as ExportFormat,
            label: 'CSV',
            color: 'border-green-200 hover:border-green-400',
            iconColor: 'text-green-600',
          },
          {
            icon: FileText,
            title: 'Markdown Dossier',
            description: 'Human-readable evidence dossier in Markdown format — suitable for conversion to Word/PDF for regulatory review',
            format: 'md' as ExportFormat,
            label: 'MD',
            color: 'border-violet-200 hover:border-violet-400',
            iconColor: 'text-violet-600',
          },
          {
            icon: FolderTree,
            title: 'eCTD Module 4 Folder Map',
            description: 'Text representation of the proposed eCTD Module 4 folder structure with document placement rationale',
            format: 'txt' as ExportFormat,
            label: 'TXT',
            color: 'border-amber-200 hover:border-amber-400',
            iconColor: 'text-amber-600',
          },
        ].map((item) => {
          const Icon = item.icon;
          const isDownloading = downloadingFormat === item.format;
          return (
            <div key={item.title} className={`card p-5 border-2 transition-colors ${item.color}`}>
              <div className="flex items-start gap-4">
                <div className={`w-10 h-10 rounded-lg bg-slate-50 flex items-center justify-center flex-shrink-0`}>
                  <Icon className={`w-5 h-5 ${item.iconColor}`} />
                </div>
                <div className="flex-1">
                  <div className="flex items-center gap-2 mb-1">
                    <h4 className="font-semibold text-slate-800 text-sm">{item.title}</h4>
                    <span className="badge bg-slate-100 text-slate-600 text-xs font-mono">{item.label}</span>
                  </div>
                  <p className="text-xs text-slate-500 leading-relaxed mb-3">{item.description}</p>
                  <button
                    className="btn-primary text-xs py-1.5"
                    onClick={() => void handleDownload(item.format)}
                    disabled={isBlocked || downloadingFormat !== null}
                  >
                    <Download className="w-3.5 h-3.5" />
                    {isDownloading ? `Preparing ${item.label}...` : `Download ${item.label}`}
                  </button>
                </div>
              </div>
            </div>
          );
        })}
      </div>

      {/* NAM-CORE reusable exports */}
      <div className="card p-5 border border-slate-200">
        <h3 className="text-sm font-semibold text-slate-800 mb-1">NAM-CORE reusable exports</h3>
        <p className="text-xs text-slate-500 mb-4">
          FAIR-oriented, machine-readable exports of the standardized NAM-CORE dataset. POC
          standardization — requires qualified human review; not an official submission standard.
        </p>

        <div className="grid grid-cols-2 gap-3">
          {[
            { icon: FileJson, label: 'JSON-LD', path: 'jsonld', desc: 'Linked-data JSON export' },
            { icon: Share2, label: 'RDF/Turtle', path: 'turtle', desc: 'RDF graph in Turtle syntax' },
            { icon: Boxes, label: 'ISA-Tab ZIP', path: 'isa-tab', desc: 'ISA-Tab experimental metadata' },
            { icon: Database, label: 'Parquet', path: 'parquet', desc: 'Columnar dataset for analytics' },
          ].map((item) => {
            const Icon = item.icon;
            return (
              <a
                key={item.path}
                href={`${API}/api/v1/projects/${project.id}/exports/${item.path}`}
                className="flex items-center gap-3 p-3 rounded-lg border border-slate-200 hover:border-blue-400 transition-colors"
              >
                <div className="w-9 h-9 rounded-lg bg-slate-50 flex items-center justify-center flex-shrink-0">
                  <Icon className="w-4 h-4 text-slate-600" />
                </div>
                <div className="flex-1 min-w-0">
                  <div className="flex items-center gap-2">
                    <span className="text-sm font-medium text-slate-800">{item.label}</span>
                    <Download className="w-3.5 h-3.5 text-slate-400" />
                  </div>
                  <p className="text-xs text-slate-500">{item.desc}</p>
                </div>
              </a>
            );
          })}
        </div>

        <p className="text-xs text-slate-500 mt-3">
          ISA-Tab is provided for experimental metadata interoperability, not regulatory submission.
        </p>

        {/* RO-Crate */}
        <div className="mt-5 border-t border-slate-200 pt-4">
          <p className="text-xs font-semibold text-slate-700 mb-2">RO-Crate bundle includes:</p>
          <ul className="list-disc list-inside text-xs text-slate-500 space-y-0.5 mb-3">
            <li>NAM-CORE JSON</li>
            <li>JSON-LD</li>
            <li>Endpoint CSV</li>
            <li>Validation report</li>
            <li>Readiness report</li>
            <li>Markdown dossier</li>
            <li>eCTD TXT</li>
            <li>Provenance</li>
          </ul>
          <a
            href={`${API}/api/v1/projects/${project.id}/exports/ro-crate`}
            className="btn-primary text-xs py-1.5 inline-flex"
          >
            <Package className="w-3.5 h-3.5" />
            Download RO-Crate ZIP
          </a>
        </div>
      </div>

      {/* Disclaimer */}
      <div className="p-4 bg-slate-50 rounded-xl border border-slate-200 text-xs text-slate-500 leading-relaxed">
        <strong className="text-slate-700">Disclaimer:</strong> All outputs generated by this tool are for{' '}
        <em>internal evidence organisation purposes only</em>. They do not constitute a regulatory submission,
        regulatory advice, or a guarantee of acceptance by any regulatory authority. Human review by qualified
        regulatory professionals is required before any material is included in an IND, eCTD, or other
        regulatory filing. NAM-derived data should be presented to regulators with full transparency about
        model limitations and context of use.
      </div>
    </div>
  );
}
