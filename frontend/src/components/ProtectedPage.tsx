'use client';

import { useEffect, type ReactNode } from 'react';
import { useRouter } from 'next/navigation';
import { useAuth } from '@/features/auth/AuthProvider';
import { Notice } from '@/components/Notice';

export function ProtectedPage({ children }: { children: ReactNode }) {
  const { user, loading, error } = useAuth();
  const router = useRouter();

  useEffect(() => {
    if (!loading && !user && !error) router.replace('/login');
  }, [loading, user, error, router]);

  if (loading) return <p className="state-message" role="status">Checking your account…</p>;
  if (error) return <Notice>{error}</Notice>;
  if (!user) return <p className="state-message" role="status">Redirecting to sign in…</p>;
  return <>{children}</>;
}
