'use client';

import { useParams } from 'next/navigation';
import { useStore } from '@/lib/store';
import ValidationMatrix from '@/components/ValidationMatrix';

export default function ValidationPage() {
  const { id } = useParams<{ id: string }>();
  const { getStudy, getEvidenceItems, updateEvidenceStatus } = useStore();

  const study = getStudy(id);
  const items = study ? getEvidenceItems(study.study_id) : [];

  return (
    <div className="p-8 max-w-6xl">
      <div className="mb-6">
        <h1 className="text-2xl font-bold text-slate-900">Validation Evidence Matrix</h1>
        <p className="text-sm text-slate-500 mt-1">
          Eight-domain validation framework assessing model fitness-for-purpose for the declared context
          of use. All criteria must be assessed before export.
        </p>
      </div>

      {items.length === 0 ? (
        <div className="text-slate-500">No evidence items found. Create a NAM study first.</div>
      ) : (
        <ValidationMatrix items={items} onStatusChange={updateEvidenceStatus} />
      )}
    </div>
  );
}
