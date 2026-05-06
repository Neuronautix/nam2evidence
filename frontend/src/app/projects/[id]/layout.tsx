'use client';

import Sidebar from '@/components/Sidebar';
import { useStore } from '@/lib/store';
import { useParams } from 'next/navigation';

export default function ProjectLayout({ children }: { children: React.ReactNode }) {
  const params = useParams<{ id: string }>();
  const { getProject } = useStore();
  const project = getProject(params.id);

  return (
    <div className="flex min-h-screen">
      <Sidebar
        projectId={params.id}
        projectName={project?.name ?? 'Project'}
      />
      <main className="flex-1 overflow-auto">
        {children}
      </main>
    </div>
  );
}
