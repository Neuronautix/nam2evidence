import type { Metadata } from 'next';
import './globals.css';
import { StoreProvider } from '@/lib/store';

export const metadata: Metadata = {
  title: 'nam2evidence',
  description: 'Context-of-use-driven evidence packaging tool for NAM-derived nonclinical data',
};

export default function RootLayout({ children }: { children: React.ReactNode }) {
  return (
    <html lang="en">
      <body className="min-h-screen bg-slate-50 text-slate-900 antialiased">
        <StoreProvider>{children}</StoreProvider>
      </body>
    </html>
  );
}
