import type { Metadata } from 'next';
import type { ReactNode } from 'react';
import { AuthProvider } from '@/features/auth/AuthProvider';
import { SiteHeader } from '@/components/SiteHeader';
import './globals.css';

export const metadata: Metadata = {
  title: 'ETS Connect | Language test sessions',
  description: 'Browse and reserve language test sessions.',
};

export default function RootLayout({ children }: { children: ReactNode }) {
  return (
    <html lang="en">
      <body>
        <AuthProvider>
          <SiteHeader />
          <main className="container">{children}</main>
          <footer className="site-footer">ETS Connect · Language test booking</footer>
        </AuthProvider>
      </body>
    </html>
  );
}
