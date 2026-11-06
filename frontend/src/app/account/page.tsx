import { ProtectedPage } from '@/components/ProtectedPage';
import { PageTitle } from '@/components/PageTitle';
import { AccountForm } from '@/features/account/AccountForm';

export default function AccountPage() {
  return <ProtectedPage><PageTitle eyebrow="Your profile" title="Account details" description="Keep your contact information up to date." /><AccountForm /></ProtectedPage>;
}
