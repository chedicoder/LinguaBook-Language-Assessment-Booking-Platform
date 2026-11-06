import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { ReservationsView } from '@/features/reservations/ReservationsView';
import { api, apiData } from '@/lib/api';
import type { Reservation } from '@/types/api';

const mockGet = jest.mocked(api.get);
const mockApiData = jest.mocked(apiData);
const reservation: Reservation = {
  id: 'reservation-1',
  sessionId: 'session-1',
  session: {
    id: 'session-1',
    language: 'French',
    startsAt: '2027-02-01T10:00:00+00:00',
    location: 'Lyon',
    capacity: 6,
    reservedCount: 3,
    availablePlaces: 3,
  },
  reservedAt: '2026-11-01T10:00:00+00:00',
  status: 'active',
};

jest.mock('@/lib/api', () => ({
  api: { get: jest.fn() },
  apiData: jest.fn(),
  getApiError: (error: Error) => error.message,
}));

describe('reservation management', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    window.confirm = jest.fn(() => true);
    mockGet.mockResolvedValue({
      data: { data: [reservation], meta: { page: 1, limit: 10, total: 1, pages: 1 }, error: null },
    } as never);
  });

  it('shows the user reservation and cancels it after confirmation', async () => {
    mockApiData.mockImplementation(async () => undefined as never);
    render(<ReservationsView />);

    expect(await screen.findByText('French')).toBeInTheDocument();
    fireEvent.click(screen.getByRole('button', { name: 'Cancel reservation' }));
    await waitFor(() => expect(mockApiData).toHaveBeenCalledWith(expect.objectContaining({
      url: '/reservations/reservation-1',
      method: 'delete',
    })));
    expect(window.confirm).toHaveBeenCalled();
    await waitFor(() => expect(screen.getByRole('status')).toHaveTextContent('Reservation cancelled.'));
  });
});
