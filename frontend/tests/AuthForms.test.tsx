import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { LoginForm, RegisterForm } from '@/features/auth/AuthForms';

const mockLogin = jest.fn();
const mockRegister = jest.fn();
const mockPush = jest.fn();

jest.mock('@/features/auth/AuthProvider', () => ({
  useAuth: () => ({ login: mockLogin, register: mockRegister }),
}));

jest.mock('next/navigation', () => ({
  useRouter: () => ({ push: mockPush }),
}));

describe('authentication forms', () => {
  beforeEach(() => {
    jest.clearAllMocks();
  });

  it('submits login credentials and navigates to the dashboard', async () => {
    mockLogin.mockResolvedValue(undefined);
    render(<LoginForm />);

    fireEvent.change(screen.getByLabelText('Email'), { target: { value: 'alex@example.com' } });
    fireEvent.change(screen.getByLabelText('Password'), { target: { value: 'correct horse battery' } });
    fireEvent.click(screen.getByRole('button', { name: 'Sign in' }));

    await waitFor(() => expect(mockLogin).toHaveBeenCalledWith('alex@example.com', 'correct horse battery'));
    expect(mockPush).toHaveBeenCalledWith('/dashboard');
  });

  it('shows a rejected login message instead of navigating', async () => {
    mockLogin.mockRejectedValue(new Error('Email or password is incorrect.'));
    render(<LoginForm />);

    fireEvent.change(screen.getByLabelText('Email'), { target: { value: 'alex@example.com' } });
    fireEvent.change(screen.getByLabelText('Password'), { target: { value: 'incorrect password' } });
    fireEvent.click(screen.getByRole('button', { name: 'Sign in' }));

    expect(await screen.findByRole('alert')).toHaveTextContent('Email or password is incorrect.');
    expect(mockPush).not.toHaveBeenCalled();
  });

  it('requires a 12-character password before registration can submit', () => {
    render(<RegisterForm />);
    expect(screen.getByLabelText('Password')).toHaveAttribute('minLength', '12');
    expect(screen.getByRole('button', { name: 'Create account' })).toBeEnabled();
  });

  it('submits registration and navigates to the dashboard', async () => {
    mockRegister.mockResolvedValue(undefined);
    render(<RegisterForm />);
    fireEvent.change(screen.getByLabelText('Full name'), { target: { value: 'Alex Chen' } });
    fireEvent.change(screen.getByLabelText('Email'), { target: { value: 'alex@example.com' } });
    fireEvent.change(screen.getByLabelText('Password'), { target: { value: 'a-valid-long-password' } });
    fireEvent.click(screen.getByRole('button', { name: 'Create account' }));
    await waitFor(() => expect(mockRegister).toHaveBeenCalledWith(
      'Alex Chen',
      'alex@example.com',
      'a-valid-long-password',
    ));
    expect(mockPush).toHaveBeenCalledWith('/dashboard');
  });
});
