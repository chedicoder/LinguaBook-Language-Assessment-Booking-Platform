import Link from 'next/link';
import { RegisterForm } from '@/features/auth/AuthForms';
import { PageTitle } from '@/components/PageTitle';

export default function RegisterPage() {
  return <section className="narrow-page">
    <PageTitle eyebrow="Get started" title="Create your account" description="Register to reserve and manage test sessions." />
    <RegisterForm />
    <p className="form-footnote">Already registered? <Link href="/login">Sign in</Link>.</p>
  </section>;
}
