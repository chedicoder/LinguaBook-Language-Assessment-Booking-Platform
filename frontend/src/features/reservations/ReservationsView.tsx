'use client';

import { useCallback, useEffect, useState } from 'react';
import { api, apiData, getApiError } from '@/lib/api';
import { formatDate } from '@/lib/format';
import { Notice } from '@/components/Notice';
import { PageTitle } from '@/components/PageTitle';
import type { ApiEnvelope, PageMeta, Reservation } from '@/types/api';

export function ReservationsView() {
  const [items, setItems] = useState<Reservation[]>([]);
  const [meta, setMeta] = useState<PageMeta>({ page: 1, limit: 10, total: 0, pages: 0 });
  const [page, setPage] = useState(1);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');

  const load = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const response = await api.get<ApiEnvelope<Reservation[]>>('/reservations', { params: { page, limit: 10 } });
      setItems(response.data.data ?? []);
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
  }, [page]);

  useEffect(() => { void load(); }, [load]);

  async function cancel(reservation: Reservation) {
    if (!window.confirm('Cancel this reservation? The place will become available to another user.')) return;
    setError('');
    setNotice('');
    try {
      await apiData({ url: `/reservations/${reservation.id}`, method: 'delete' });
      setNotice('Reservation cancelled.');
      await load();
    } catch (reason) {
      setError(getApiError(reason));
      await load();
    }
  }

  return (
    <>
      <PageTitle eyebrow="Your bookings" title="Reservations" description="Review your bookings and cancel a reservation if your plans change." />
      {notice && <Notice kind="success">{notice}</Notice>}
      {error && <Notice>{error}</Notice>}
      {loading ? <p className="state-message" role="status">Loading reservations…</p> : items.length === 0 ? (
        <p className="empty-state">You have no reservations yet. Browse sessions to find a test date.</p>
      ) : (
        <div className="stack">
          {items.map((reservation) => (
            <article className="reservation-row" key={reservation.id}>
              <div>
                <span className={`pill ${reservation.status === 'cancelled' ? 'pill-muted' : ''}`}>{reservation.status}</span>
                <h2>{reservation.session?.language ?? 'Session unavailable'}</h2>
                <p>{reservation.session ? formatDate(reservation.session.startsAt) : 'Details are no longer available'}</p>
                {reservation.session && <p className="muted">{reservation.session.location}</p>}
                <small className="muted">Reserved {formatDate(reservation.reservedAt)}</small>
              </div>
              {reservation.status === 'active' && reservation.session && (
                <button className="button button-danger" type="button" onClick={() => void cancel(reservation)}>Cancel reservation</button>
              )}
            </article>
          ))}
        </div>
      )}
      {!loading && meta.pages > 1 && (
        <nav className="pagination" aria-label="Reservation pages">
          <button type="button" className="button button-secondary" disabled={page <= 1} onClick={() => setPage((value) => value - 1)}>Previous</button>
          <span>Page {page} of {meta.pages}</span>
          <button type="button" className="button button-secondary" disabled={page >= meta.pages} onClick={() => setPage((value) => value + 1)}>Next</button>
        </nav>
      )}
    </>
  );
}
