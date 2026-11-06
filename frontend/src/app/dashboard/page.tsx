import { ProtectedPage } from '@/components/ProtectedPage';
import { DashboardView } from '@/features/dashboard/DashboardView';

export default function DashboardPage() {
  return <ProtectedPage><DashboardView /></ProtectedPage>;
}
