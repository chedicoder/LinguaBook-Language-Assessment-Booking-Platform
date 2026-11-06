
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { SessionsView } from '@/features/sessions/SessionsView';
import type { TestSession } from '@/types/api';
import { api, apiData } from '@/lib/api';

const mockPush = jest.fn();
const mockGet = jest.mocked(api.get);
const mockApiData = jest.mocked(apiData);

const testSession: TestSession = {
  id: 'session-1',
  language: 'English',
  startsAt: '2027-01-12T09:00:00+00:00',
  location: 'Paris',
  capacity: 4,
  reservedCount: 2,
  availablePlaces: 2,
};

jest.mock('@/features/auth/AuthProvider', () => ({
  useAuth: () => ({
    user: {
      id: 'user-1',
      name: 'Alex',
      email: 'alex@example.com',
      roles: ['ROLE_USER'],
    },
    refresh: jest.fn(),
  }),
}));

jest.mock('next/navigation', () => ({
  useRouter: () => ({ push: mockPush }),
}));

jest.mock('@/lib/api', () => ({
  api: { get: jest.fn() },
  apiData: jest.fn(),
  getApiError: (error: Error) => error.message,
}));

describe('session listing', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    window.confirm = jest.fn(() => true);
  });

  it('renders sessions and requests the selected page', async () => {
    mockGet.mockResolvedValue({
      data: {
        data: [testSession],
        meta: { page: 1, limit: 10, total: 11, pages: 2 },
        error: null,
      },
    } as never);

    render(<SessionsView />);

    expect(await screen.findByText('English')).toBeInTheDocument();
    expect(screen.getByText('Paris')).toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: 'Next' }));

    await waitFor(() =>
      expect(mockGet).toHaveBeenLastCalledWith(
        '/sessions',
        expect.objectContaining({
          params: expect.objectContaining({ page: 2 }),
        }),
      ),
    );
  });

  it('confirms and submits a booking request', async () => {
    mockGet.mockResolvedValue({
      data: {
        data: [testSession],
        meta: { page: 1, limit: 10, total: 1, pages: 1 },
        error: null,
      },
    } as never);

    mockApiData.mockResolvedValue({
      reservation: { id: 'reservation-1' },
    } as never);

    render(<SessionsView />);

    fireEvent.click(
      await screen.findByRole('button', { name: 'Reserve a place' }),
    );

    await waitFor(() =>
      expect(mockApiData).toHaveBeenCalledWith(
        expect.objectContaining({
          url: '/reservations',
          data: { sessionId: 'session-1' },
        }),
      ),
    );

    expect(window.confirm).toHaveBeenCalled();

    await waitFor(() =>
      expect(screen.getByRole('status')).toHaveTextContent(
        'Your place is reserved.',
      ),
    );
  });

  it('shows a loading state and reports a session API error', async () => {
    let rejectLoad: ((reason: Error) => void) | undefined;

    mockGet.mockImplementation(
      () =>
        new Promise((_, reject) => {
          rejectLoad = reject;
        }) as never,
    );

    render(<SessionsView />);

    expect(screen.getByRole('status')).toHaveTextContent(
      'Loading sessions',
    );

    rejectLoad?.(new Error('Sessions are temporarily unavailable.'));

    expect(await screen.findByRole('alert')).toHaveTextContent(
      'Sessions are temporarily unavailable.',
    );
  });
});
