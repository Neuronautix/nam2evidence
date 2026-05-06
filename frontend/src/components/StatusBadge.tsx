import clsx from 'clsx';
import { EvidenceStatus } from '@/lib/types';

interface StatusBadgeProps {
  status: EvidenceStatus | string;
  size?: 'sm' | 'md';
}

const config: Record<string, { label: string; classes: string }> = {
  met: { label: 'Met', classes: 'bg-green-100 text-green-800' },
  partial: { label: 'Partial', classes: 'bg-amber-100 text-amber-800' },
  not_met: { label: 'Not Met', classes: 'bg-red-100 text-red-800' },
  not_applicable: { label: 'N/A', classes: 'bg-slate-100 text-slate-600' },
  pending: { label: 'Pending', classes: 'bg-slate-100 text-slate-600' },
  human_review_required: { label: 'Needs Review', classes: 'bg-amber-100 text-amber-800' },
  approved: { label: 'Approved', classes: 'bg-green-100 text-green-800' },
  rejected: { label: 'Rejected', classes: 'bg-red-100 text-red-800' },
};

export default function StatusBadge({ status, size = 'md' }: StatusBadgeProps) {
  const c = config[status] ?? { label: status, classes: 'bg-slate-100 text-slate-600' };
  return (
    <span
      className={clsx(
        'badge font-medium',
        c.classes,
        size === 'sm' ? 'text-xs px-2 py-0.5' : 'text-xs px-2.5 py-1'
      )}
    >
      {c.label}
    </span>
  );
}
