'use client';

import { ClaimNode, ClaimEdge } from '@/lib/types';
import ConfidenceBadge from './ConfidenceBadge';
import StatusBadge from './StatusBadge';
import { useState } from 'react';
import { ChevronDown, ChevronRight, Shield, AlertTriangle } from 'lucide-react';

interface ClaimGraphProps {
  nodes: ClaimNode[];
  edges: ClaimEdge[];
  onStatusChange?: (claimId: string, status: ClaimNode['review_status']) => void;
}

interface TreeNode {
  claim: ClaimNode;
  children: TreeNode[];
}

function buildTree(nodes: ClaimNode[]): TreeNode[] {
  const roots = nodes.filter((n) => !n.parent_claim_id);
  function buildChildren(parentId: string): TreeNode[] {
    return nodes
      .filter((n) => n.parent_claim_id === parentId)
      .map((n) => ({ claim: n, children: buildChildren(n.claim_id) }));
  }
  return roots.map((n) => ({ claim: n, children: buildChildren(n.claim_id) }));
}

function ClaimCard({
  node,
  depth,
  onStatusChange,
}: {
  node: TreeNode;
  depth: number;
  onStatusChange?: (claimId: string, status: ClaimNode['review_status']) => void;
}) {
  const [expanded, setExpanded] = useState(depth < 2);
  const { claim, children } = node;

  return (
    <div className={depth > 0 ? 'ml-6 border-l-2 border-slate-200 pl-4' : ''}>
      <div className="card p-4 mb-3">
        <div className="flex items-start justify-between gap-3">
          <div className="flex-1">
            <div className="flex items-center gap-2 mb-2 flex-wrap">
              <span className="text-xs font-mono text-slate-400">{claim.claim_id}</span>
              <ConfidenceBadge level={claim.confidence} size="sm" />
              <StatusBadge status={claim.review_status} size="sm" />
              <span className="badge bg-slate-100 text-slate-600 text-xs">{claim.claim_type}</span>
            </div>
            <p className="text-sm text-slate-800 font-medium leading-relaxed">{claim.claim_text}</p>

            {expanded && (
              <div className="mt-3 space-y-2">
                {claim.supporting_evidence.length > 0 && (
                  <div className="flex items-start gap-2">
                    <Shield className="w-3.5 h-3.5 text-green-500 mt-0.5 flex-shrink-0" />
                    <p className="text-xs text-slate-500">
                      <span className="font-medium text-slate-600">Supporting: </span>
                      {claim.supporting_evidence.join(', ')}
                    </p>
                  </div>
                )}
                {claim.contradictory_evidence.length > 0 && (
                  <div className="flex items-start gap-2">
                    <AlertTriangle className="w-3.5 h-3.5 text-red-400 mt-0.5 flex-shrink-0" />
                    <p className="text-xs text-slate-500">
                      <span className="font-medium text-slate-600">Contradictory: </span>
                      {claim.contradictory_evidence.join(', ')}
                    </p>
                  </div>
                )}
                {claim.limitations.length > 0 && (
                  <div className="mt-2 space-y-1">
                    {claim.limitations.map((lim, i) => (
                      <p key={i} className="text-xs text-amber-700 bg-amber-50 rounded px-2 py-1">
                        ⚠ {lim}
                      </p>
                    ))}
                  </div>
                )}
                <div className="flex items-center gap-2 mt-2 flex-wrap">
                  <span className="text-xs text-slate-400">eCTD targets:</span>
                  {claim.ectd_target_sections.map((s) => (
                    <span
                      key={s}
                      className="badge bg-blue-50 text-blue-700 text-xs font-mono"
                    >
                      {s}
                    </span>
                  ))}
                </div>
              </div>
            )}
          </div>

          <div className="flex flex-col gap-2 items-end flex-shrink-0">
            {onStatusChange && claim.review_status === 'human_review_required' && (
              <div className="flex gap-1">
                <button
                  className="px-2 py-1 text-xs rounded bg-green-600 text-white hover:bg-green-700 transition-colors"
                  onClick={() => onStatusChange(claim.claim_id, 'approved')}
                >
                  Approve
                </button>
                <button
                  className="px-2 py-1 text-xs rounded bg-red-600 text-white hover:bg-red-700 transition-colors"
                  onClick={() => onStatusChange(claim.claim_id, 'rejected')}
                >
                  Reject
                </button>
              </div>
            )}
            {onStatusChange && claim.review_status === 'approved' && (
              <button
                className="px-2 py-1 text-xs rounded bg-slate-200 text-slate-600 hover:bg-slate-300 transition-colors"
                onClick={() => onStatusChange(claim.claim_id, 'human_review_required')}
              >
                Reopen
              </button>
            )}
            <button
              onClick={() => setExpanded((e) => !e)}
              className="text-slate-400 hover:text-slate-600 transition-colors"
            >
              {expanded ? <ChevronDown className="w-4 h-4" /> : <ChevronRight className="w-4 h-4" />}
            </button>
          </div>
        </div>
      </div>

      {expanded &&
        children.map((child) => (
          <ClaimCard
            key={child.claim.claim_id}
            node={child}
            depth={depth + 1}
            onStatusChange={onStatusChange}
          />
        ))}
    </div>
  );
}

export default function ClaimGraph({ nodes, edges, onStatusChange }: ClaimGraphProps) {
  const tree = buildTree(nodes);

  const approvedCount = nodes.filter((n) => n.review_status === 'approved').length;
  const pendingCount = nodes.filter((n) => n.review_status === 'human_review_required').length;

  return (
    <div>
      {/* Summary strip */}
      <div className="flex items-center gap-4 mb-6 p-4 bg-slate-50 rounded-xl border border-slate-200">
        <div className="text-sm">
          <span className="font-medium text-slate-900">{nodes.length}</span>
          <span className="text-slate-500 ml-1">claims total</span>
        </div>
        <div className="text-sm">
          <span className="font-medium text-green-700">{approvedCount}</span>
          <span className="text-slate-500 ml-1">approved</span>
        </div>
        <div className="text-sm">
          <span className="font-medium text-amber-700">{pendingCount}</span>
          <span className="text-slate-500 ml-1">awaiting review</span>
        </div>
        <div className="text-sm">
          <span className="font-medium text-slate-600">{edges.length}</span>
          <span className="text-slate-500 ml-1">evidence links</span>
        </div>
        {pendingCount > 0 && (
          <div className="ml-auto px-3 py-1.5 bg-amber-50 border border-amber-200 rounded-lg text-xs text-amber-800 font-medium">
            ⚠ Human review required before export
          </div>
        )}
      </div>

      {/* Legend */}
      <div className="flex items-center gap-4 mb-5 flex-wrap">
        <span className="text-xs text-slate-500 font-medium">Confidence:</span>
        {(['exploratory', 'supportive', 'decision_informing', 'potentially_pivotal'] as const).map(
          (level) => (
            <ConfidenceBadge key={level} level={level} size="sm" />
          )
        )}
      </div>

      {/* Tree */}
      <div className="space-y-1">
        {tree.map((node) => (
          <ClaimCard key={node.claim.claim_id} node={node} depth={0} onStatusChange={onStatusChange} />
        ))}
      </div>

      {/* Edge list */}
      {edges.length > 0 && (
        <div className="mt-6 card p-4">
          <h4 className="text-sm font-semibold text-slate-700 mb-3">Evidence Links ({edges.length})</h4>
          <div className="space-y-1.5">
            {edges.map((edge, i) => {
              let relClasses: string;
              switch (edge.relationship) {
                case 'supports':
                  relClasses = 'bg-green-100 text-green-700';
                  break;
                case 'contradicts':
                case 'refutes':
                  relClasses = 'bg-red-100 text-red-700';
                  break;
                case 'qualifies':
                case 'limited_by':
                  relClasses = 'bg-amber-100 text-amber-700';
                  break;
                case 'depends_on':
                case 'requires':
                case 'derived_from':
                  relClasses = 'bg-blue-100 text-blue-700';
                  break;
                case 'conforms_to':
                  relClasses = 'bg-teal-100 text-teal-700';
                  break;
                case 'maps_to_ectd_section':
                  relClasses = 'bg-violet-100 text-violet-700';
                  break;
                default:
                  relClasses = 'bg-slate-100 text-slate-700';
              }
              return (
                <div key={i} className="flex items-center gap-2 text-xs text-slate-600">
                  <span className="font-mono bg-slate-100 px-1.5 py-0.5 rounded">{edge.from_claim_id}</span>
                  <span className={`px-1.5 py-0.5 rounded text-xs font-medium ${relClasses}`}>
                    {edge.relationship} →
                  </span>
                  <span className="font-mono bg-slate-100 px-1.5 py-0.5 rounded">{edge.to_claim_id}</span>
                </div>
              );
            })}
          </div>
        </div>
      )}
    </div>
  );
}
