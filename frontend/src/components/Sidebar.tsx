'use client';

import Link from 'next/link';
import { usePathname } from 'next/navigation';
import {
  FlaskConical,
  FileText,
  Microscope,
  CheckSquare,
  GitBranch,
  FolderTree,
  Download,
  ChevronLeft,
  Home,
} from 'lucide-react';
import clsx from 'clsx';

interface SidebarProps {
  projectId: string;
  projectName: string;
}

const navItems = [
  { href: '', label: 'Overview', icon: Home },
  { href: '/cou', label: 'Context of Use', icon: FileText },
  { href: '/study', label: 'NAM Study', icon: Microscope },
  { href: '/validation', label: 'Validation Matrix', icon: CheckSquare },
  { href: '/claims', label: 'Claim Graph', icon: GitBranch },
  { href: '/ectd', label: 'eCTD Mapping', icon: FolderTree },
  { href: '/export', label: 'Export Center', icon: Download },
];

export default function Sidebar({ projectId, projectName }: SidebarProps) {
  const pathname = usePathname();
  const base = `/projects/${projectId}`;

  return (
    <aside className="w-60 min-h-screen bg-slate-900 flex flex-col flex-shrink-0">
      {/* Logo */}
      <div className="px-4 py-5 border-b border-slate-700">
        <div className="flex items-center gap-2.5 mb-3">
          <div className="w-8 h-8 rounded-lg bg-blue-600 flex items-center justify-center">
            <FlaskConical className="w-4 h-4 text-white" />
          </div>
          <span className="text-sm font-semibold text-white">NAMO Mapper</span>
        </div>
        <Link
          href="/"
          className="flex items-center gap-1.5 text-xs text-slate-400 hover:text-slate-200 transition-colors"
        >
          <ChevronLeft className="w-3 h-3" />
          All Projects
        </Link>
      </div>

      {/* Project name */}
      <div className="px-4 py-4 border-b border-slate-700">
        <p className="text-xs text-slate-500 uppercase tracking-wide mb-1">Project</p>
        <p className="text-sm text-white font-medium leading-tight line-clamp-2">{projectName}</p>
      </div>

      {/* Nav */}
      <nav className="flex-1 px-2 py-4 space-y-0.5">
        {navItems.map((item) => {
          const href = base + item.href;
          const isActive =
            item.href === '' ? pathname === base : pathname.startsWith(href);
          const Icon = item.icon;

          return (
            <Link
              key={item.href}
              href={href}
              className={clsx(
                'flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition-colors',
                isActive
                  ? 'bg-blue-600 text-white font-medium'
                  : 'text-slate-400 hover:text-white hover:bg-slate-800'
              )}
            >
              <Icon className="w-4 h-4 flex-shrink-0" />
              {item.label}
            </Link>
          );
        })}
      </nav>

      {/* Footer */}
      <div className="px-4 py-4 border-t border-slate-700">
        <p className="text-xs text-slate-500 text-center">NAM Evidence Packaging Tool</p>
      </div>
    </aside>
  );
}
