'use client';

import { ECTDMapping } from '@/lib/types';
import { ectdModule4Tree } from '@/lib/demoData';
import { FolderOpen, FileText, ChevronDown, ChevronRight, Pencil, X } from 'lucide-react';
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
  onEdit?: (mapping: ECTDMapping) => void;
  onDelete?: (mappingId: string) => void;
}

function TreeNodeRow({
  node,
  depth,
  mappedSections,
  mappings,
  onEdit,
  onDelete,
}: {
  node: ECTDTreeNode;
  depth: number;
  mappedSections: Set<string>;
  mappings: ECTDMapping[];
  onEdit?: (mapping: ECTDMapping) => void;
  onDelete?: (mappingId: string) => void;
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
                {m.confidence && (
                  <span
                    className={clsx(
                      'badge text-xs',
                      m.confidence === 'high' && 'bg-green-100 text-green-700',
                      m.confidence === 'medium' && 'bg-amber-100 text-amber-700',
                      m.confidence === 'low' && 'bg-slate-100 text-slate-600'
                    )}
                  >
                    {m.confidence} confidence
                  </span>
                )}
                <div className="ml-auto flex items-center gap-1">
                  {onEdit && (
                    <button
                      type="button"
                      onClick={() => onEdit(m)}
                      title="Edit mapping"
                      className="p-1 rounded hover:bg-slate-100 text-slate-500 hover:text-slate-800"
                    >
                      <Pencil className="w-3.5 h-3.5" />
                    </button>
                  )}
                  {onDelete && (
                    <button
                      type="button"
                      onClick={() => onDelete(m.mapping_id)}
                      title="Delete mapping"
                      className="p-1 rounded hover:bg-red-50 text-slate-500 hover:text-red-700"
                    >
                      <X className="w-3.5 h-3.5" />
                    </button>
                  )}
                </div>
              </div>
              {m.document_title && (
                <p className="text-xs text-slate-700 font-medium mb-1">{m.document_title}</p>
              )}
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
            onEdit={onEdit}
            onDelete={onDelete}
          />
        ))}
    </div>
  );
}

export default function ECTDTree({ mappings, onEdit, onDelete }: ECTDTreeProps) {
  const mappedSections = new Set(mappings.map((m) => m.ectd_section));

  // Render mappings whose section is not represented in the static tree as an
  // additional "Other mapped sections" group, so user-added mappings remain visible.
  const treeSections = collectAllSections(ectdModule4Tree as ECTDTreeNode[]);
  const orphanMappings = mappings.filter((m) => !treeSections.has(m.ectd_section));
  const orphanSectionSet = new Set(orphanMappings.map((m) => m.ectd_section));

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
        {(ectdModule4Tree as ECTDTreeNode[]).map((node) => (
          <TreeNodeRow
            key={node.section}
            node={node}
            depth={0}
            mappedSections={mappedSections}
            mappings={mappings}
            onEdit={onEdit}
            onDelete={onDelete}
          />
        ))}

        {orphanSectionSet.size > 0 && (
          <div className="mt-4 pt-3 border-t border-slate-200">
            <p className="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2 px-2">
              Other mapped sections
            </p>
            {Array.from(orphanSectionSet).map((section) => (
              <TreeNodeRow
                key={section}
                node={{ section, title: '(custom section)', children: [] }}
                depth={0}
                mappedSections={mappedSections}
                mappings={mappings}
                onEdit={onEdit}
                onDelete={onDelete}
              />
            ))}
          </div>
        )}
      </div>
    </div>
  );
}

function collectAllSections(nodes: ECTDTreeNode[]): Set<string> {
  const out = new Set<string>();
  function walk(n: ECTDTreeNode) {
    out.add(n.section);
    n.children.forEach(walk);
  }
  nodes.forEach(walk);
  return out;
}
