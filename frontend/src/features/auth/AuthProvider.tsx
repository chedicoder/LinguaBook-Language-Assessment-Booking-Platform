'use client';

import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';
import type { ReactNode } from 'react';
import { api, apiData, getApiError, isUnauthorized } from '@/lib/api';
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

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<User | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const refresh = useCallback(async () => {
    try {
      const result = await apiData<{ user: User }>({ url: '/auth/me' });
      setUser(result.user);
      setError(null);
    } catch (error) {
      if (isUnauthorized(error)) {
        setUser(null);
      } else {
        setUser(null);
        setError(getApiError(error));
        throw error;
      }
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void refresh().catch(() => undefined);
  }, [refresh]);

  const login = useCallback(async (email: string, password: string) => {
    const result = await apiData<{ user: User }>({ url: '/auth/login', method: 'post', data: { email, password } });
    setUser(result.user);
    setError(null);
  }, []);

  const register = useCallback(async (name: string, email: string, password: string) => {
    await apiData<{ user: User }>({ url: '/auth/register', method: 'post', data: { name, email, password } });
    await login(email, password);
  }, [login]);

  const logout = useCallback(async () => {
    await api.post('/auth/logout');
    setUser(null);
  }, []);

  const value = useMemo(() => ({ user, loading, error, refresh, login, register, logout }), [
    user, loading, error, refresh, login, register, logout,
  ]);
  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth(): AuthContextValue {
  const context = useContext(AuthContext);
  if (!context) throw new Error('useAuth must be used within AuthProvider.');
  return context;
}
