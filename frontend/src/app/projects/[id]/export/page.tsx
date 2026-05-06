'use client';

import { useParams } from 'next/navigation';
import { useStore } from '@/lib/store';
import ExportCenter from '@/components/ExportCenter';

export default function ExportPage() {
  const { id } = useParams<{ id: string }>();
  const { getProject, getCOU, getStudy, getEvidenceItems, getClaimNodes, getECTDMappings } = useStore();

  const project = getProject(id);
  const cou = getCOU(id);
  const study = getStudy(id);
  const evidenceItems = study ? getEvidenceItems(study.study_id) : [];
  const claimNodes = getClaimNodes(id);
  const ectdMappings = getECTDMappings(id);

  if (!project || !cou || !study) {
    return (
      <div className="p-8 text-slate-500">
        Complete the Context of Use, NAM Study, and Claim Graph workspaces before exporting.
      </div>
    );
  }

  return (
    <div className="p-8 max-w-4xl">
      <div className="mb-6">
        <h1 className="text-2xl font-bold text-slate-900">Export Center</h1>
        <p className="text-sm text-slate-500 mt-1">
          Package the complete NAM evidence dossier for regulatory use. All claims must be reviewed
          and approved before export is enabled.
        </p>
      </div>

      <ExportCenter
        project={project}
        cou={cou}
        study={study}
        evidenceItems={evidenceItems}
        claimNodes={claimNodes}
        ectdMappings={ectdMappings}
      />
    </div>
  );
}
