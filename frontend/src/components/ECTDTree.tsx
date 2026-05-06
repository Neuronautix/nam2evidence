'use client';

import { ECTDMapping } from '@/lib/types';
import { ectdModule4Tree } from '@/lib/demoData';
import { FolderOpen, FileText, ChevronDown, ChevronRight } from 'lucide-react';
import { useState } from 'react';
import clsx from 'clsx';

interface ECTDTreeNode {
  section: string;
  title: string;
  children: ECTDTreeNode[];
  mapped?: boolean;
}

interface ECTDTreeProps {
  mappings: ECTDMapping[];
}

function TreeNodeRow({
  node,
  depth,
  mappedSections,
  mappings,
}: {
  node: ECTDTreeNode;
  depth: number;
  mappedSections: Set<string>;
  mappings: ECTDMapping[];
}) {
  const isMapped = mappedSections.has(node.section);
  const hasChildren = node.children.length > 0;
  const [expanded, setExpanded] = useState(depth < 2 || isMapped);

  const relevantMappings = mappings.filter((m) => m.ectd_section === node.section);

  return (
    <div>
      <div
        className={clsx(
          'flex items-start gap-2 py-1.5 px-2 rounded-lg transition-colors',
          isMapped ? 'bg-blue-50 border border-blue-200' : 'hover:bg-slate-50',
          depth === 0 ? 'font-semibold' : ''
        )}
        style={{ paddingLeft: `${8 + depth * 16}px` }}
      >
        <button
          onClick={() => hasChildren && setExpanded((e) => !e)}
          className="flex items-center gap-1.5 flex-1 text-left"
          disabled={!hasChildren}
        >
          {hasChildren ? (
            expanded ? (
              <ChevronDown className="w-3.5 h-3.5 text-slate-400 flex-shrink-0" />
            ) : (
              <ChevronRight className="w-3.5 h-3.5 text-slate-400 flex-shrink-0" />
            )
          ) : (
            <span className="w-3.5 h-3.5" />
          )}
          {hasChildren ? (
            <FolderOpen className={clsx('w-3.5 h-3.5 flex-shrink-0', isMapped ? 'text-blue-600' : 'text-amber-500')} />
          ) : (
            <FileText className={clsx('w-3.5 h-3.5 flex-shrink-0', isMapped ? 'text-blue-600' : 'text-slate-400')} />
          )}
          <span className={clsx('text-sm', isMapped ? 'text-blue-800 font-medium' : 'text-slate-700')}>
            <span className="font-mono text-xs mr-1.5 text-slate-400">{node.section}</span>
            {node.title}
          </span>
          {isMapped && (
            <span className="ml-2 badge bg-blue-600 text-white text-xs">
              {relevantMappings.length} mapped
            </span>
          )}
        </button>
      </div>

      {/* Mapping details */}
      {isMapped && expanded && relevantMappings.length > 0 && (
        <div className="ml-10 mb-2 space-y-2">
          {relevantMappings.map((m) => (
            <div key={m.mapping_id} className="bg-white border border-blue-100 rounded-lg p-3">
              <div className="flex items-center gap-2 mb-1 flex-wrap">
                <span className="text-xs font-mono text-slate-400">{m.mapping_id}</span>
                <span className="badge bg-teal-100 text-teal-700 text-xs">{m.evidence_type}</span>
                {m.study_id && (
                  <span className="badge bg-slate-100 text-slate-600 text-xs font-mono">{m.study_id}</span>
                )}
                {m.claim_id && (
                  <span className="badge bg-violet-100 text-violet-700 text-xs font-mono">{m.claim_id}</span>
                )}
              </div>
              <p className="text-xs text-slate-600 leading-relaxed">{m.notes}</p>
              {m.justification && (
                <p className="text-xs text-slate-500 italic mt-1 leading-relaxed">
                  <span className="font-medium not-italic">Rationale: </span>{m.justification}
                </p>
              )}
            </div>
          ))}
        </div>
      )}

      {/* Children */}
      {hasChildren && expanded &&
        node.children.map((child) => (
          <TreeNodeRow
            key={child.section}
            node={child}
            depth={depth + 1}
            mappedSections={mappedSections}
            mappings={mappings}
          />
        ))}
    </div>
  );
}

export default function ECTDTree({ mappings }: ECTDTreeProps) {
  const mappedSections = new Set(mappings.map((m) => m.ectd_section));

  return (
    <div>
      {/* Summary */}
      <div className="flex items-center gap-4 mb-5 p-4 bg-slate-50 rounded-xl border border-slate-200">
        <div className="text-sm">
          <span className="font-medium text-blue-700">{mappings.length}</span>
          <span className="text-slate-500 ml-1">documents mapped</span>
        </div>
        <div className="text-sm">
          <span className="font-medium text-slate-700">{mappedSections.size}</span>
          <span className="text-slate-500 ml-1">eCTD sections targeted</span>
        </div>
        <div className="flex flex-wrap gap-1 ml-auto">
          {Array.from(mappedSections).map((s) => (
            <span key={s} className="badge bg-blue-100 text-blue-700 font-mono text-xs">{s}</span>
          ))}
        </div>
      </div>

      {/* Tree */}
      <div className="card p-3">
        {ectdModule4Tree.map((node) => (
          <TreeNodeRow
            key={node.section}
            node={node as ECTDTreeNode}
            depth={0}
            mappedSections={mappedSections}
            mappings={mappings}
          />
        ))}
      </div>
    </div>
  );
}
