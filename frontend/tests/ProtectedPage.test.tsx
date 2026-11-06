import { render, screen, waitFor } from '@testing-library/react';
import { ProtectedPage } from '@/components/ProtectedPage';

const mockPush = jest.fn();
let mockAuth = { user: null as null | { id: string }, loading: false };

jest.mock('@/features/auth/AuthProvider', () => ({
  useAuth: () => mockAuth,
}));
jest.mock('next/navigation', () => ({ useRouter: () => ({ replace: mockPush }) }));

describe('protected page', () => {
  beforeEach(() => {
    mockPush.mockClear();
    mockAuth = { user: null, loading: false };
  });

  it('redirects unauthenticated visitors to sign in', async () => {
    render(<ProtectedPage><p>Private details</p></ProtectedPage>);
    await waitFor(() => expect(mockPush).toHaveBeenCalledWith('/login'));
    expect(screen.queryByText('Private details')).not.toBeInTheDocument();
  });

  it('shows the page after authentication is loaded', () => {
    mockAuth = { user: { id: 'user-1' }, loading: false };
    render(<ProtectedPage><p>Private details</p></ProtectedPage>);
    expect(screen.getByText('Private details')).toBeInTheDocument();
  });
});
