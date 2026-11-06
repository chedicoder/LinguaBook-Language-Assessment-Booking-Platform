'use client';

import { useCallback, useEffect, useState } from 'react';
import type { FormEvent } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { api, apiData, getApiError } from '@/lib/api';
import { formatDate } from '@/lib/format';
import { useAuth } from '@/features/auth/AuthProvider';
import { Notice } from '@/components/Notice';
import { PageTitle } from '@/components/PageTitle';
import type { ApiEnvelope, PageMeta, TestSession } from '@/types/api';

export function SessionsView() {
  const { user } = useAuth();
  const router = useRouter();
  const [sessions, setSessions] = useState<TestSession[]>([]);
  const [meta, setMeta] = useState<PageMeta>({ page: 1, limit: 10, total: 0, pages: 0 });
  const [page, setPage] = useState(1);
  const [language, setLanguage] = useState('');
  const [availableOnly, setAvailableOnly] = useState(false);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');
  const [booking, setBooking] = useState<string | null>(null);
  const [creating, setCreating] = useState(false);
  const [newLanguage, setNewLanguage] = useState('');
  const [newStartsAt, setNewStartsAt] = useState('');
  const [newLocation, setNewLocation] = useState('');
  const [newCapacity, setNewCapacity] = useState('');
  const isAdmin = user?.roles?.includes('ROLE_ADMIN') ?? false;

  const loadSessions = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const response = await api.get<ApiEnvelope<TestSession[]>>('/sessions', {
        params: { page, limit: 10, language: language || undefined, available: availableOnly },
      });
      setSessions(response.data.data ?? []);
      setMeta({
        page: Number(response.data.meta.page ?? page),
        limit: Number(response.data.meta.limit ?? 10),
        total: Number(response.data.meta.total ?? 0),
        pages: Number(response.data.meta.pages ?? 0),
      });
    } catch (reason) {
      setError(getApiError(reason));
    } finally {
      setLoading(false);
    }
  }, [page, language, availableOnly]);

  useEffect(() => { void loadSessions(); }, [loadSessions]);

  async function createSession(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setCreating(true);
    setNotice('');
    setError('');
    try {
      await apiData<{ session: TestSession }>({
        url: '/sessions',
        method: 'post',
        data: {
          language: newLanguage,
          startsAt: new Date(newStartsAt).toISOString(),
          location: newLocation,
          capacity: Number(newCapacity),
        },
      });
      setNewLanguage('');
      setNewStartsAt('');
      setNewLocation('');
      setNewCapacity('');
      setNotice('The session was created successfully.');
      await loadSessions();
    } catch (reason) {
      setError(getApiError(reason));
    } finally {
      setCreating(false);
    }
  }

  async function book(session: TestSession) {
    if (!user) {
      router.push('/login');
      return;
    }
    if (!window.confirm(`Reserve a place for ${session.language} on ${formatDate(session.startsAt)}?`)) return;
    setBooking(session.id);
    setNotice('');
    setError('');
    try {
      await apiData({ url: '/reservations', method: 'post', data: { sessionId: session.id } });
      setNotice('Your place is reserved. View your reservations to see the details.');
      await loadSessions();
    } catch (reason) {
      setError(getApiError(reason));
    } finally {
      setBooking(null);
    }
  }

  return (
    <>
      <PageTitle eyebrow="Find your session" title="Upcoming test sessions" description="Browse available language test dates and locations." />
      {isAdmin && (
        <section className="section-block" aria-labelledby="create-session-heading">
          <h2 id="create-session-heading">Create a test session</h2>
          <form className="form-card" onSubmit={(event) => void createSession(event)}>
            <label htmlFor="new-session-language">Language</label>
            <input id="new-session-language" value={newLanguage} onChange={(event) => setNewLanguage(event.target.value)} maxLength={80} required />
            <label htmlFor="new-session-starts-at">Date and time</label>
            <input
              id="new-session-starts-at"
              type="datetime-local"
              value={newStartsAt}
              onChange={(event) => setNewStartsAt(event.target.value)}
              min={new Date(Date.now() - new Date().getTimezoneOffset() * 60000).toISOString().slice(0, 16)}
              required
            />
            <small>Date and time are interpreted in your local timezone.</small>
            <label htmlFor="new-session-location">Location</label>
            <input id="new-session-location" value={newLocation} onChange={(event) => setNewLocation(event.target.value)} maxLength={240} required />
            <label htmlFor="new-session-capacity">Capacity</label>
            <input
              id="new-session-capacity"
              type="number"
              min={1}
              max={10000}
              step={1}
              value={newCapacity}
              onChange={(event) => setNewCapacity(event.target.value)}
              required
            />
            <button className="button" type="submit" disabled={creating}>
              {creating ? 'Creating session…' : 'Create session'}
            </button>
          </form>
        </section>
      )}
      <div className="filters">
        <label>
          Language
          <input aria-label="Filter by language" value={language} onChange={(event) => { setLanguage(event.target.value); setPage(1); }} placeholder="e.g. English" />
        </label>
        <label className="checkbox-label">
          <input type="checkbox" checked={availableOnly} onChange={(event) => { setAvailableOnly(event.target.checked); setPage(1); }} />
          Available places only
        </label>
      </div>
      {notice && <Notice kind="success">{notice}</Notice>}
      {error && <Notice>{error}</Notice>}
      {loading ? <p className="state-message" role="status">Loading sessions…</p> : sessions.length === 0 ? (
        <p className="empty-state">No sessions match those filters. Try changing your search.</p>
      ) : (
        <div className="card-grid">
          {sessions.map((session) => (
            <article className="session-card" key={session.id}>
              <div className="card-topline"><span className="pill">{session.language}</span><span>{session.availablePlaces} places left</span></div>
              <h2>{formatDate(session.startsAt)}</h2>
              <p>{session.location}</p>
              <p className="muted">{session.reservedCount} of {session.capacity} places reserved</p>
              <button className="button" type="button" disabled={session.availablePlaces < 1 || booking === session.id}
                onClick={() => void book(session)}>
                {booking === session.id ? 'Reserving…' : session.availablePlaces < 1 ? 'Full' : user ? 'Reserve a place' : 'Sign in to reserve'}
              </button>
            </article>
          ))}
        </div>
      )}
      {!loading && meta.pages > 1 && (
        <nav className="pagination" aria-label="Session pages">
          <button type="button" className="button button-secondary" disabled={page <= 1} onClick={() => setPage((value) => value - 1)}>Previous</button>
          <span>Page {page} of {meta.pages}</span>
          <button type="button" className="button button-secondary" disabled={page >= meta.pages} onClick={() => setPage((value) => value + 1)}>Next</button>
        </nav>
      )}
      <p className="muted small-note">Need an account? <Link href="/register">Register here</Link>.</p>
    </>
  );
}
