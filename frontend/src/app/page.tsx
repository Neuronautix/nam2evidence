'use client';

import { useState } from 'react';
import Link from 'next/link';
import { useStore } from '@/lib/store';
import { FlaskConical, Plus, ChevronRight, Clock, CheckCircle, AlertCircle } from 'lucide-react';
import { Project } from '@/lib/types';

function ReviewStatusIcon({ status }: { status: Project['review_status'] }) {
  if (status === 'approved') return <CheckCircle className="w-4 h-4 text-green-500" />;
  if (status === 'rejected') return <AlertCircle className="w-4 h-4 text-red-500" />;
  if (status === 'human_review_required') return <AlertCircle className="w-4 h-4 text-amber-500" />;
  return <Clock className="w-4 h-4 text-slate-400" />;
}

export default function HomePage() {
  const { state, createProject } = useStore();
  const [showForm, setShowForm] = useState(false);
  const [form, setForm] = useState({ name: '', description: '', drug_name: '', sponsor: '' });

  function handleCreate(e: React.FormEvent) {
    e.preventDefault();
    const newProject: Project = {
      id: `proj-${Date.now()}`,
      ...form,
      created_at: new Date().toISOString(),
      updated_at: new Date().toISOString(),
      review_status: 'pending',
    };
    createProject(newProject);
    setShowForm(false);
    setForm({ name: '', description: '', drug_name: '', sponsor: '' });
  }

  return (
    <div className="min-h-screen bg-slate-50">
      {/* Header */}
      <header className="bg-white border-b border-slate-200 px-6 py-4">
        <div className="max-w-5xl mx-auto flex items-center justify-between">
          <div className="flex items-center gap-3">
            <div className="w-9 h-9 rounded-lg bg-blue-600 flex items-center justify-center">
              <FlaskConical className="w-5 h-5 text-white" />
            </div>
            <div>
              <h1 className="text-base font-semibold text-slate-900">NAMO-to-IND Mapper</h1>
              <p className="text-xs text-slate-500">NAM Evidence Packaging Tool</p>
            </div>
          </div>
          <button
            onClick={() => setShowForm(true)}
            className="btn-primary"
          >
            <Plus className="w-4 h-4" />
            New Project
          </button>
        </div>
      </header>

      <main className="max-w-5xl mx-auto px-6 py-10">
        {/* Hero */}
        <div className="mb-10">
          <h2 className="text-2xl font-bold text-slate-900 mb-2">Evidence Packaging Dashboard</h2>
          <p className="text-slate-500 max-w-2xl">
            Package NAM-derived nonclinical data into context-of-use-driven evidence dossiers aligned
            with IND/eCTD submission requirements. Each project organises evidence across five
            structured workspaces.
          </p>
        </div>

        {/* Workspaces Info */}
        <div className="grid grid-cols-5 gap-3 mb-10">
          {[
            { label: 'Context of Use', color: 'bg-blue-100 text-blue-700', desc: 'COU Card' },
            { label: 'NAM Study', color: 'bg-teal-100 text-teal-700', desc: 'NAMO Metadata' },
            { label: 'Validation', color: 'bg-violet-100 text-violet-700', desc: 'Evidence Matrix' },
            { label: 'Claims', color: 'bg-amber-100 text-amber-700', desc: 'WoE Graph' },
            { label: 'eCTD Module 4', color: 'bg-green-100 text-green-700', desc: 'Mapping' },
          ].map((w) => (
            <div key={w.label} className="card p-3 text-center">
              <span className={`badge ${w.color} mb-1`}>{w.label}</span>
              <p className="text-xs text-slate-500 mt-1">{w.desc}</p>
            </div>
          ))}
        </div>

        {/* Projects List */}
        <h3 className="text-sm font-semibold text-slate-500 uppercase tracking-wide mb-4">
          Projects ({state.projects.length})
        </h3>

        <div className="space-y-3">
          {state.projects.map((project) => (
            <Link
              key={project.id}
              href={`/projects/${project.id}`}
              className="card p-5 flex items-center justify-between hover:border-blue-300 hover:shadow-md transition-all group block"
            >
              <div className="flex items-start gap-4">
                <div className="w-10 h-10 rounded-lg bg-blue-50 flex items-center justify-center flex-shrink-0 mt-0.5">
                  <FlaskConical className="w-5 h-5 text-blue-600" />
                </div>
                <div>
                  <div className="flex items-center gap-2 mb-1">
                    <h4 className="font-semibold text-slate-900 group-hover:text-blue-700 transition-colors">
                      {project.name}
                    </h4>
                    <ReviewStatusIcon status={project.review_status} />
                  </div>
                  <p className="text-sm text-slate-500 mb-2">{project.description}</p>
                  <div className="flex items-center gap-4 text-xs text-slate-400">
                    <span>Drug: <span className="text-slate-600 font-medium">{project.drug_name}</span></span>
                    <span>Sponsor: <span className="text-slate-600 font-medium">{project.sponsor}</span></span>
                    <span>Updated: <span className="text-slate-600">{new Date(project.updated_at).toLocaleDateString()}</span></span>
                  </div>
                </div>
              </div>
              <ChevronRight className="w-5 h-5 text-slate-300 group-hover:text-blue-400 flex-shrink-0" />
            </Link>
          ))}
        </div>

        {/* Create Project Modal */}
        {showForm && (
          <div className="fixed inset-0 bg-black/40 backdrop-blur-sm flex items-center justify-center z-50 p-4">
            <div className="card w-full max-w-lg p-6">
              <h3 className="text-lg font-semibold text-slate-900 mb-5">Create New Project</h3>
              <form onSubmit={handleCreate} className="space-y-4">
                <div>
                  <label className="label">Project Name *</label>
                  <input
                    required
                    className="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                    value={form.name}
                    onChange={(e) => setForm({ ...form, name: e.target.value })}
                    placeholder="e.g. Hepatotoxicity Liability – CompoundY"
                  />
                </div>
                <div>
                  <label className="label">Drug / Compound Name *</label>
                  <input
                    required
                    className="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                    value={form.drug_name}
                    onChange={(e) => setForm({ ...form, drug_name: e.target.value })}
                    placeholder="e.g. CompoundY (CY-0012)"
                  />
                </div>
                <div>
                  <label className="label">Sponsor</label>
                  <input
                    className="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                    value={form.sponsor}
                    onChange={(e) => setForm({ ...form, sponsor: e.target.value })}
                    placeholder="e.g. Acme Pharma Ltd."
                  />
                </div>
                <div>
                  <label className="label">Description</label>
                  <textarea
                    rows={3}
                    className="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none"
                    value={form.description}
                    onChange={(e) => setForm({ ...form, description: e.target.value })}
                    placeholder="Brief description of the evidence packaging goal…"
                  />
                </div>
                <div className="flex justify-end gap-3 pt-2">
                  <button type="button" className="btn-secondary" onClick={() => setShowForm(false)}>
                    Cancel
                  </button>
                  <button type="submit" className="btn-primary">
                    Create Project
                  </button>
                </div>
              </form>
            </div>
          </div>
        )}
      </main>
    </div>
  );
}
