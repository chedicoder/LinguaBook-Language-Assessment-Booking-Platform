'use client';

import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { useAuth } from '@/features/auth/AuthProvider';
import { useState } from 'react';
import { getApiError } from '@/lib/api';
import { Notice } from '@/components/Notice';

export function SiteHeader() {
  const { user, logout } = useAuth();
  const router = useRouter();
  const [error, setError] = useState('');

  async function signOut() {
    setError('');
    try {
      await logout();
      router.push('/login');
    } catch (reason) {
      setError(getApiError(reason));
    }
  }

  return (
    <>
      {error && <div className="container"><Notice>{error}</Notice></div>}
      <header className="site-header">
        <Link className="brand" href="/">ETS<span>Connect</span></Link>
        <nav aria-label="Main navigation">
          <Link href="/sessions">Sessions</Link>
          {user ? (
            <>
              <Link href="/dashboard">Dashboard</Link>
              <Link href="/reservations">Reservations</Link>
              <Link href="/account">Account</Link>
              <button className="link-button" type="button" onClick={() => void signOut()}>Sign out</button>
            </>
          ) : (
            <>
              <Link href="/login">Sign in</Link>
              <Link className="nav-cta" href="/register">Create account</Link>
            </>
          )}
        </nav>
      </header>
    </>
  );
}
