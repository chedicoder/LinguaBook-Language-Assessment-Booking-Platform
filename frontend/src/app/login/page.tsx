import Link from 'next/link';
import { LoginForm } from '@/features/auth/AuthForms';
import { PageTitle } from '@/components/PageTitle';

export default function LoginPage() {
  return <section className="narrow-page">
    <PageTitle eyebrow="Welcome back" title="Sign in" description="Access your test sessions and reservations." />
    <LoginForm />
    <p className="form-footnote">New to ETS Connect? <Link href="/register">Create an account</Link>.</p>
  </section>;
}
