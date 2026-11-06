
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { AccountForm } from '@/features/account/AccountForm';
import { apiData } from '@/lib/api';

const mockRefresh = jest.fn();
const mockApiData = jest.mocked(apiData);

jest.mock('@/features/auth/AuthProvider', () => {
  const user = {
    id: 'user-1',
    name: 'Alex Chen',
    email: 'alex@example.com',
    roles: ['ROLE_USER'],
  };

  return {
    useAuth: () => ({
      user,
      refresh: mockRefresh,
    }),
  };
});

jest.mock('@/lib/api', () => ({
  apiData: jest.fn(),
  getApiError: (error: Error) => error.message,
}));

describe('account form', () => {
  beforeEach(() => {
    jest.clearAllMocks();
  });

  it('validates and saves the profile, then refreshes the current user', async () => {
    mockApiData.mockResolvedValue({ user: {} } as never);

    render(<AccountForm />);

    const nameInput = screen.getByLabelText('Full name');
    const emailInput = screen.getByLabelText('Email');

    fireEvent.change(nameInput, {
      target: { value: 'Alex Chen' },
    });

    fireEvent.change(emailInput, {
      target: { value: 'alex.new@example.com' },
    });

    expect(nameInput).toHaveValue('Alex Chen');
    expect(emailInput).toHaveValue('alex.new@example.com');

    fireEvent.click(screen.getByRole('button', { name: 'Save changes' }));

    await waitFor(() => {
      expect(mockApiData).toHaveBeenCalledWith(
        expect.objectContaining({
          url: '/users/me',
          method: 'patch',
          data: {
            name: 'Alex Chen',
            email: 'alex.new@example.com',
          },
        }),
      );
    });

    expect(mockRefresh).toHaveBeenCalledTimes(1);

    expect(await screen.findByRole('status')).toHaveTextContent(
      'Your account details have been updated.',
    );
  });
});
