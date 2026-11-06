
'use client';

import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
} from 'react';
import type { ReactNode } from 'react';
import { api, apiData, getApiError } from '@/lib/api';
import type { User } from '@/types/api';

interface AuthContextValue {
  user: User | null;
  loading: boolean;
  error: string | null;
  refresh: () => Promise<void>;
  login: (email: string, password: string) => Promise<void>;
  register: (name: string, email: string, password: string) => Promise<void>;
  logout: () => Promise<void>;
}

const AuthContext = createContext<AuthContextValue | null>(null);

const AUTH_SESSION_KEY = 'ets_auth_session';

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<User | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const refresh = useCallback(async () => {
    if (typeof window === 'undefined') {
      return;
    }

    // Aucune session connue : ne pas appeler l'API.
    if (localStorage.getItem(AUTH_SESSION_KEY) !== 'true') {
      setUser(null);
      setError(null);
      setLoading(false);
      return;
    }

    setLoading(true);

    try {
      const result = await apiData<{ user: User }>({
        url: '/auth/me',
      });

      setUser(result.user);
      setError(null);
    } catch (err) {
      setUser(null);

      // Session expirée ou invalide.
      if (
        typeof err === 'object' &&
        err !== null &&
        'response' in err &&
        (err as { response?: { status?: number } }).response?.status === 401
      ) {
        localStorage.removeItem(AUTH_SESSION_KEY);
        setError(null);
      } else {
        setError(getApiError(err));
      }
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void refresh();
  }, [refresh]);

  const login = useCallback(async (email: string, password: string) => {
    const result = await apiData<{ user: User }>({
      url: '/auth/login',
      method: 'post',
      data: { email, password },
    });

    localStorage.setItem(AUTH_SESSION_KEY, 'true');
    setUser(result.user);
    setError(null);
  }, []);

  const register = useCallback(async (name: string, email: string, password: string) => {
    await apiData<{ user: User }>({
      url: '/auth/register',
      method: 'post',
      data: { name, email, password },
    });

    await login(email, password);
  }, [login]);

  const logout = useCallback(async () => {
    try {
      await api.post('/auth/logout');
    } finally {
      localStorage.removeItem(AUTH_SESSION_KEY);
      setUser(null);
      setError(null);
    }
  }, []);

  const value = useMemo(
    () => ({
      user,
      loading,
      error,
      refresh,
      login,
      register,
      logout,
    }),
    [user, loading, error, refresh, login, register, logout],
  );

  return (
    <AuthContext.Provider value={value}>
      {children}
    </AuthContext.Provider>
  );
}

export function useAuth(): AuthContextValue {
  const context = useContext(AuthContext);

  if (!context) {
    throw new Error('useAuth must be used within AuthProvider.');
  }

  return context;
}
