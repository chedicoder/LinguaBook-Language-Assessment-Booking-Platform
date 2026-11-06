import Link from 'next/link';
import { PageTitle } from '@/components/PageTitle';

export default function HomePage() {
  return (
    <section className="hero">
      <div>
        <p className="eyebrow">Your next step starts here</p>
        <h1>Find the right test session for you.</h1>
        <p className="hero-copy">Explore upcoming language test dates, compare locations, and reserve your place securely.</p>
        <div className="actions">
          <Link className="button" href="/sessions">Browse sessions</Link>
          <Link className="button button-secondary" href="/register">Create an account</Link>
        </div>
      </div>
      <aside className="hero-card">
        <PageTitle eyebrow="ETS Connect" title="Plan with confidence" description="Manage your reservations and account in one place." />
      </aside>
    </section>
  );
}
