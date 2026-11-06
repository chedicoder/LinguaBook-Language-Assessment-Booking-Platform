'use client';

import { useEffect, useState, type FormEvent } from 'react';
import { useAuth } from '@/features/auth/AuthProvider';
import { apiData, getApiError } from '@/lib/api';
import { Notice } from '@/components/Notice';

export function AccountForm() {
  const { user, refresh } = useAuth();
  const [name, setName] = useState(user?.name ?? '');
  const [email, setEmail] = useState(user?.email ?? '');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');

  useEffect(() => {
    setName(user?.name ?? '');
    setEmail(user?.email ?? '');
  }, [user]);

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setBusy(true);
    setError('');
    setNotice('');
    try {
      await apiData({ url: '/users/me', method: 'patch', data: { name, email } });
      await refresh();
      setNotice('Your account details have been updated.');
    } catch (reason) {
      setError(getApiError(reason));
    } finally {
      setBusy(false);
    }
  }

  return (
    <form className="form-card" onSubmit={submit}>
      {error && <Notice>{error}</Notice>}
      {notice && <Notice kind="success">{notice}</Notice>}
      <label htmlFor="account-name">Full name</label>
      <input id="account-name" required maxLength={120} value={name} onChange={(event) => setName(event.target.value)} />
      <label htmlFor="account-email">Email</label>
      <input id="account-email" type="email" required maxLength={180} value={email} onChange={(event) => setEmail(event.target.value)} />
      <button className="button" type="submit" disabled={busy}>{busy ? 'Saving…' : 'Save changes'}</button>
    </form>
  );
}
