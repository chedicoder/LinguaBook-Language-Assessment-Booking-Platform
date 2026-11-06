export interface ApiEnvelope<T> {
  data: T | null;
  meta: Record<string, unknown>;
  error: { code: string; message: string; details?: Record<string, string[]> } | null;
}

export interface User {
  id: string;
  name: string;
  email: string;
  roles: string[];
  createdAt: string;
  updatedAt: string;
}

export interface TestSession {
  id: string;
  language: string;
  startsAt: string;
  location: string;
  capacity: number;
  reservedCount: number;
  availablePlaces: number;
}

export interface Reservation {
  id: string;
  sessionId: string;
  session: TestSession | null;
  reservedAt: string;
  status: 'active' | 'cancelled';
}

export interface PageMeta {
  page: number;
  limit: number;
  total: number;
  pages: number;
}
