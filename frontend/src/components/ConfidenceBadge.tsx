import clsx from 'clsx';
import { RegulatoryConfidenceLevel } from '@/lib/types';

interface ConfidenceBadgeProps {
  level: RegulatoryConfidenceLevel;
  size?: 'sm' | 'md';
}

const config: Record<RegulatoryConfidenceLevel, { label: string; classes: string }> = {
  exploratory: {
    label: 'Exploratory',
    classes: 'bg-yellow-100 text-yellow-800 border border-yellow-200',
  },
  supportive: {
    label: 'Supportive',
    classes: 'bg-blue-100 text-blue-800 border border-blue-200',
  },
  decision_informing: {
    label: 'Decision-Informing',
    classes: 'bg-green-100 text-green-800 border border-green-200',
  },
  potentially_pivotal: {
    label: 'Potentially Pivotal',
    classes: 'bg-purple-100 text-purple-800 border border-purple-200',
  },
};

export default function ConfidenceBadge({ level, size = 'md' }: ConfidenceBadgeProps) {
  const c = config[level];
  return (
    <span
      className={clsx(
        'badge font-semibold',
        c.classes,
        size === 'sm' ? 'text-xs px-2 py-0.5' : 'text-xs px-2.5 py-1'
      )}
    >
      {c.label}
    </span>
  );
}
