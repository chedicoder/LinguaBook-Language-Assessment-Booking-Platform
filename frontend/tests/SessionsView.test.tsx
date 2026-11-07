
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { SessionsView } from '@/features/sessions/SessionsView';
import type { TestSession } from '@/types/api';
import { api, apiData } from '@/lib/api';

const mockPush = jest.fn();
const mockGet = jest.mocked(api.get);
const mockDelete = jest.mocked(api.delete);
const mockApiData = jest.mocked(apiData);
const mockRoles = ['ROLE_USER'];

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
      roles: mockRoles,
    },
    refresh: jest.fn(),
  }),
}));

jest.mock('next/navigation', () => ({
  useRouter: () => ({ push: mockPush }),
}));

jest.mock('@/lib/api', () => ({
  api: { get: jest.fn(), delete: jest.fn() },
  apiData: jest.fn(),
  getApiError: (error: Error) => error.message,
}));

describe('session listing', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    mockRoles.splice(0, mockRoles.length, 'ROLE_USER');
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

  it('allows admins to delete a session and refreshes the list', async () => {
    mockRoles.push('ROLE_ADMIN');
    mockGet
      .mockResolvedValueOnce({
        data: {
          data: [testSession],
          meta: { page: 1, limit: 10, total: 1, pages: 1 },
          error: null,
        },
      } as never)
      .mockResolvedValueOnce({
        data: {
          data: [],
          meta: { page: 1, limit: 10, total: 0, pages: 0 },
          error: null,
        },
      } as never);
    mockDelete.mockResolvedValue({} as never);

    render(<SessionsView />);

    fireEvent.click(await screen.findByRole('button', { name: 'Delete session' }));

    expect(window.confirm).toHaveBeenCalledWith(
      expect.stringContaining('Delete the session English'),
    );
    await waitFor(() => expect(mockDelete).toHaveBeenCalledWith('/sessions/session-1'));
    expect(await screen.findByText('The session was deleted successfully.')).toBeInTheDocument();
    expect(screen.queryByText('Paris')).not.toBeInTheDocument();
  });

  it('does not show the delete action to regular users', async () => {
    mockGet.mockResolvedValue({
      data: {
        data: [testSession],
        meta: { page: 1, limit: 10, total: 1, pages: 1 },
        error: null,
      },
    } as never);

    render(<SessionsView />);

    await screen.findByText('English');
    expect(screen.queryByRole('button', { name: 'Delete session' })).not.toBeInTheDocument();
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
