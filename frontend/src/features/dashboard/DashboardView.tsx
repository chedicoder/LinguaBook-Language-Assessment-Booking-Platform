'use client';

import Link from 'next/link';
import { useEffect, useState } from 'react';
import { apiData, getApiError } from '@/lib/api';
import { formatDate } from '@/lib/format';
import { useAuth } from '@/features/auth/AuthProvider';
import { Notice } from '@/components/Notice';
import { PageTitle } from '@/components/PageTitle';
import type { Reservation } from '@/types/api';

export function DashboardView() {
  const { user } = useAuth();
  const [reservations, setReservations] = useState<Reservation[]>([]);
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    apiData<Reservation[]>({ url: '/reservations', params: { limit: 50 } })
      .then(setReservations)
      .catch((reason: unknown) => setError(getApiError(reason)))
      .finally(() => setLoading(false));
  }, []);

  const upcoming = reservations.filter((item) => item.status === 'active'
    && item.session && new Date(item.session.startsAt).getTime() > Date.now());
  return (
    <>
      <PageTitle eyebrow="Your dashboard" title={`Welcome, ${user?.name ?? 'there'}`} description="Your language test plans at a glance." />
      {error && <Notice>{error}</Notice>}
      <div className="summary-grid">
        <article className="summary-card"><span>Reservations</span><strong>{loading ? '—' : reservations.filter((item) => item.status === 'active').length}</strong></article>
        <article className="summary-card"><span>Upcoming</span><strong>{loading ? '—' : upcoming.length}</strong></article>
      </div>
      <section className="section-block">
        <div className="section-heading"><h2>Upcoming reservations</h2><Link href="/reservations">View all</Link></div>
        {loading ? <p className="state-message" role="status">Loading your reservations…</p> : upcoming.length === 0 ? (
          <p className="empty-state">No upcoming reservations. <Link href="/sessions">Browse test sessions</Link>.</p>
        ) : upcoming.slice(0, 3).map((reservation) => (
          <article className="reservation-row" key={reservation.id}>
            <div><h3>{reservation.session?.language}</h3><p>{reservation.session && formatDate(reservation.session.startsAt)}</p>
              <p className="muted">{reservation.session?.location}</p></div>
          </article>
        ))}
      </section>
      <div className="actions">
        <Link className="button" href="/sessions">Browse sessions</Link>
        <Link className="button button-secondary" href="/account">Manage account</Link>
      </div>
    </>
  );
}
