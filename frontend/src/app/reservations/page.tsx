import { ProtectedPage } from '@/components/ProtectedPage';
import { ReservationsView } from '@/features/reservations/ReservationsView';

export default function ReservationsPage() {
  return <ProtectedPage><ReservationsView /></ProtectedPage>;
}
