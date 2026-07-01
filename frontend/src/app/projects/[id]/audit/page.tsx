'use client';

import { useCallback, useEffect, useState } from 'react';
import { useParams } from 'next/navigation';
import Link from 'next/link';

const API = process.env.NEXT_PUBLIC_API_URL ?? 'http://localhost:8080';

interface AuditEntry {
  id: string;
  entity_type: string;
  entity_id: string;
  action: string;
  old_value: string | null;
  new_value: string | null;
  user_or_role: string | null;
  reason: string | null;
  timestamp: string;
}

interface AuditResponse {
  count: number;
  entries: AuditEntry[];
}

export default function AuditPage() {
  const { id } = useParams<{ id: string }>();
  const [entries, setEntries] = useState<AuditEntry[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await fetch(`${API}/api/v1/projects/${id}/audit-log`, { cache: 'no-store' });
      if (!res.ok) throw new Error(`API ${res.status}`);
      const data = (await res.json()) as AuditResponse;
      setEntries(data.entries ?? []);
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Failed to load audit log');
    } finally {
      setLoading(false);
    }
  }, [id]);

  useEffect(() => {
    void load();
  }, [load]);

  const ordered = [...entries].sort(
    (a, b) => new Date(b.timestamp).getTime() - new Date(a.timestamp).getTime()
  );

  return (
    <div className="p-8 max-w-6xl">
      <div className="mb-6">
        <h1 className="text-2xl font-bold text-slate-900">Audit Trail</h1>
        <p className="text-sm text-slate-500 mt-1">
          Reverse-chronological record of changes across NAM-CORE workspaces.
        </p>
        <p className="text-xs text-slate-500 mt-2">
          Export snapshot history is available from the{' '}
          <Link href={`/projects/${id}/export`} className="text-blue-600 hover:underline">
            Export Center
          </Link>
          .
        </p>
      </div>

      {loading ? (
        <p className="text-sm text-slate-500">Loading audit log…</p>
      ) : error ? (
        <p className="text-sm text-rose-700">{error}</p>
      ) : ordered.length === 0 ? (
        <p className="text-sm text-slate-500">No audit entries recorded.</p>
      ) : (
        <div className="rounded-xl border border-slate-200 bg-white p-5 overflow-x-auto">
          <table className="w-full text-xs border border-slate-200">
            <thead className="bg-slate-50">
              <tr>
                <th className="px-2 py-1.5 text-left font-medium text-slate-600">Timestamp</th>
                <th className="px-2 py-1.5 text-left font-medium text-slate-600">Action</th>
                <th className="px-2 py-1.5 text-left font-medium text-slate-600">Entity type</th>
                <th className="px-2 py-1.5 text-left font-medium text-slate-600">Entity ID</th>
                <th className="px-2 py-1.5 text-left font-medium text-slate-600">User / role</th>
                <th className="px-2 py-1.5 text-left font-medium text-slate-600">Reason</th>
              </tr>
            </thead>
            <tbody>
              {ordered.map((e) => (
                <tr key={e.id} className="odd:bg-white even:bg-slate-50">
                  <td className="px-2 py-1.5 text-slate-600 whitespace-nowrap">
                    {new Date(e.timestamp).toLocaleString()}
                  </td>
                  <td className="px-2 py-1.5 text-slate-700 font-mono">{e.action}</td>
                  <td className="px-2 py-1.5 text-slate-700">{e.entity_type}</td>
                  <td className="px-2 py-1.5 text-slate-600 font-mono">{e.entity_id}</td>
                  <td className="px-2 py-1.5 text-slate-700">{e.user_or_role ?? '—'}</td>
                  <td className="px-2 py-1.5 text-slate-600">{e.reason ?? '—'}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}
