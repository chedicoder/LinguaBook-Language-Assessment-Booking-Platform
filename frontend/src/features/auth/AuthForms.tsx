'use client';

import { useState, type FormEvent } from 'react';
import { useRouter } from 'next/navigation';
import { useAuth } from '@/features/auth/AuthProvider';
import { getApiError } from '@/lib/api';
import { Notice } from '@/components/Notice';

export function LoginForm() {
  const { login } = useAuth();
  const router = useRouter();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setError('');
    setBusy(true);
    try {
      await login(email, password);
      router.push('/dashboard');
    } catch (reason) {
      setError(getApiError(reason));
    } finally {
      setBusy(false);
    }
  }

  return (
    <form className="form-card" onSubmit={submit}>
      {error && <Notice>{error}</Notice>}
      <label htmlFor="email">Email</label>
      <input id="email" name="email" type="email" autoComplete="email" required maxLength={180}
        value={email} onChange={(event) => setEmail(event.target.value)} />
      <label htmlFor="password">Password</label>
      <input id="password" name="password" type="password" autoComplete="current-password" required
        value={password} onChange={(event) => setPassword(event.target.value)} />
      <button className="button full-width" type="submit" disabled={busy}>{busy ? 'Signing in…' : 'Sign in'}</button>
    </form>
  );
}

export function RegisterForm() {
  const { register } = useAuth();
  const router = useRouter();
  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setError('');
    setBusy(true);
    try {
      await register(name, email, password);
      router.push('/dashboard');
    } catch (reason) {
      setError(getApiError(reason));
    } finally {
      setBusy(false);
    }
  }

  return (
    <form className="form-card" onSubmit={submit}>
      {error && <Notice>{error}</Notice>}
      <label htmlFor="name">Full name</label>
      <input id="name" name="name" autoComplete="name" required maxLength={120}
        value={name} onChange={(event) => setName(event.target.value)} />
      <label htmlFor="email">Email</label>
      <input id="email" name="email" type="email" autoComplete="email" required maxLength={180}
        value={email} onChange={(event) => setEmail(event.target.value)} />
      <label htmlFor="password">Password</label>
      <input id="password" name="password" type="password" autoComplete="new-password" required minLength={12} maxLength={128}
        aria-describedby="password-hint" value={password} onChange={(event) => setPassword(event.target.value)} />
      <small id="password-hint">Use at least 12 characters.</small>
      <button className="button full-width" type="submit" disabled={busy}>{busy ? 'Creating account…' : 'Create account'}</button>
    </form>
  );
}
