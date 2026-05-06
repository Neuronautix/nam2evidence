'use client';

import { useParams } from 'next/navigation';
import { useStore } from '@/lib/store';
import ClaimGraph from '@/components/ClaimGraph';

export default function ClaimsPage() {
  const { id } = useParams<{ id: string }>();
  const { getClaimNodes, getClaimEdges, updateClaimStatus } = useStore();

  const nodes = getClaimNodes(id);
  const edges = getClaimEdges(id);

  return (
    <div className="p-8 max-w-4xl">
      <div className="mb-6">
        <h1 className="text-2xl font-bold text-slate-900">Weight-of-Evidence Claim Graph</h1>
        <p className="text-sm text-slate-500 mt-1">
          Structured claims linking NAM study evidence to regulatory conclusions. Each claim must be
          reviewed and approved by a qualified human reviewer before the package can be exported.
        </p>
      </div>

      {nodes.length === 0 ? (
        <div className="text-slate-500">No claims found for this project.</div>
      ) : (
        <ClaimGraph nodes={nodes} edges={edges} onStatusChange={updateClaimStatus} />
      )}
    </div>
  );
}
