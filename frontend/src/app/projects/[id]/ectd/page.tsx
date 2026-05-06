'use client';

import { useParams } from 'next/navigation';
import { useStore } from '@/lib/store';
import ECTDTree from '@/components/ECTDTree';

export default function ECTDPage() {
  const { id } = useParams<{ id: string }>();
  const { getECTDMappings } = useStore();
  const mappings = getECTDMappings(id);

  return (
    <div className="p-8 max-w-5xl">
      <div className="mb-6">
        <h1 className="text-2xl font-bold text-slate-900">eCTD Module 4 Mapper</h1>
        <p className="text-sm text-slate-500 mt-1">
          Maps NAM evidence documents and claim summaries to the appropriate sections of an
          IND/eCTD Module 4 nonclinical study report hierarchy. Highlighted sections contain mapped
          evidence.
        </p>
      </div>

      <ECTDTree mappings={mappings} />
    </div>
  );
}
