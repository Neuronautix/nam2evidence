'use client';

import { useParams } from 'next/navigation';
import { useStore } from '@/lib/store';
import Link from 'next/link';
import ConfidenceBadge from '@/components/ConfidenceBadge';
import StatusBadge from '@/components/StatusBadge';
import { FileText, Microscope, CheckSquare, GitBranch, FolderTree, Download, ChevronRight, Upload } from 'lucide-react';

export default function ProjectOverviewPage() {
  const { id } = useParams<{ id: string }>();
  const { getProject, getCOU, getStudy, getEvidenceItems, getClaimNodes, getECTDMappings } = useStore();

  const project = getProject(id);
  const cou = getCOU(id);
  const study = getStudy(id);
  const evidence = study ? getEvidenceItems(study.study_id) : [];
  const claims = getClaimNodes(id);
  const ectd = getECTDMappings(id);

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

  const evidenceMet = evidence.filter((e) => e.status === 'met').length;
  const claimsApproved = claims.filter((c) => c.review_status === 'approved').length;

  const workspaces = [
    {
      href: `/projects/${id}/cou`,
      icon: FileText,
      label: 'Context of Use',
      desc: cou ? `${cou.cou_id} · v${cou.version}` : 'Not created',
      meta: cou ? <ConfidenceBadge level={cou.regulatory_confidence_level} size="sm" /> : null,
      color: 'bg-blue-50 border-blue-200',
      iconColor: 'text-blue-600',
    },
    {
      href: `/projects/${id}/study`,
      icon: Microscope,
      label: 'NAM Study',
      desc: study ? study.title : 'Not created',
      meta: study ? <span className="badge bg-teal-100 text-teal-700 text-xs">{study.model_system.namo_class}</span> : null,
      color: 'bg-teal-50 border-teal-200',
      iconColor: 'text-teal-600',
    },
    {
      href: `/projects/${id}/import`,
      icon: Upload,
      label: 'Import NAMO',
      desc: 'Paste or upload YAML/JSON',
      meta: null,
      color: 'bg-indigo-50 border-indigo-200',
      iconColor: 'text-indigo-600',
    },
    {
      href: `/projects/${id}/validation`,
      icon: CheckSquare,
      label: 'Validation Matrix',
      desc: `${evidenceMet}/${evidence.length} criteria met`,
      meta: null,
      color: 'bg-violet-50 border-violet-200',
      iconColor: 'text-violet-600',
    },
    {
      href: `/projects/${id}/claims`,
      icon: GitBranch,
      label: 'Claim Graph',
      desc: `${claimsApproved}/${claims.length} claims approved`,
      meta: claims.some((c) => c.review_status === 'human_review_required') ? (
        <span className="badge bg-amber-100 text-amber-700 text-xs">Review required</span>
      ) : null,
      color: 'bg-amber-50 border-amber-200',
      iconColor: 'text-amber-600',
    },
    {
      href: `/projects/${id}/ectd`,
      icon: FolderTree,
      label: 'eCTD Mapping',
      desc: `${ectd.length} documents mapped`,
      meta: null,
      color: 'bg-green-50 border-green-200',
      iconColor: 'text-green-600',
    },
    {
      href: `/projects/${id}/export`,
      icon: Download,
      label: 'Export Center',
      desc: 'JSON, CSV, Markdown, eCTD folder map',
      meta: null,
      color: 'bg-slate-50 border-slate-200',
      iconColor: 'text-slate-600',
    },
  ];

  return (
    <div className="p-8 max-w-4xl">
      {/* Project header */}
      <div className="mb-8">
        <div className="flex items-start gap-4">
          <div>
            <h1 className="text-2xl font-bold text-slate-900 mb-1">{project.name}</h1>
            <p className="text-slate-500 mb-3">{project.description}</p>
            <div className="flex items-center gap-4 text-sm flex-wrap">
              <span className="text-slate-500">
                Drug: <span className="font-medium text-slate-700">{project.drug_name}</span>
              </span>
              <span className="text-slate-500">
                Sponsor: <span className="font-medium text-slate-700">{project.sponsor}</span>
              </span>
              <StatusBadge status={project.review_status} />
            </div>
          </div>
        </div>
      </div>

      {/* Workspaces */}
      <h2 className="text-sm font-semibold text-slate-500 uppercase tracking-wide mb-4">
        Evidence Workspaces
      </h2>
      <div className="grid grid-cols-2 gap-4">
        {workspaces.map((w) => {
          const Icon = w.icon;
          return (
            <Link
              key={w.href}
              href={w.href}
              className={`card p-5 border-2 ${w.color} hover:shadow-md transition-all group flex items-start justify-between gap-3`}
            >
              <div className="flex items-start gap-3">
                <div className="w-9 h-9 rounded-lg bg-white flex items-center justify-center flex-shrink-0">
                  <Icon className={`w-4 h-4 ${w.iconColor}`} />
                </div>
                <div>
                  <h3 className="font-semibold text-slate-800 text-sm mb-0.5 group-hover:text-blue-700 transition-colors">
                    {w.label}
                  </h3>
                  <p className="text-xs text-slate-500 mb-1">{w.desc}</p>
                  {w.meta}
                </div>
              </div>
              <ChevronRight className="w-4 h-4 text-slate-300 group-hover:text-blue-400 flex-shrink-0 mt-1" />
            </Link>
          );
        })}
      </div>
    </div>
  );
}
