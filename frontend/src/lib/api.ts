'use client';

import axios, { AxiosError, type AxiosRequestConfig } from 'axios';
import type { ApiEnvelope } from '@/types/api';

const baseURL = process.env.NEXT_PUBLIC_API_URL ?? 'http://localhost:8000/api';

export const api = axios.create({
  baseURL,
  withCredentials: true,
  headers: { Accept: 'application/json' },
});

let csrfTokenValue: string | null = null;
let csrfRequest: Promise<string> | undefined;

async function ensureCsrfToken(): Promise<string> {
  if (csrfTokenValue) return csrfTokenValue;
  csrfRequest ??= api.get<ApiEnvelope<{ csrfToken: string }>>('/auth/csrf')
    .then(({ data }) => {
      if (!data.data?.csrfToken) throw new Error('The server did not issue a CSRF token.');
      csrfTokenValue = data.data.csrfToken;
      return data.data.csrfToken;
    })
    .finally(() => { csrfRequest = undefined; });
  return csrfRequest;
}

api.interceptors.response.use((response) => {
  const body = response.data as ApiEnvelope<{ csrfToken?: string }> | undefined;
  if (body?.data?.csrfToken) csrfTokenValue = body.data.csrfToken;
  return response;
});

api.interceptors.request.use(async (config) => {
  if (['post', 'put', 'patch', 'delete'].includes((config.method ?? 'get').toLowerCase())) {
    const token = await ensureCsrfToken();
    config.headers.set('X-CSRF-Token', token);
  }
  return config;
});

export function getApiError(error: unknown): string {
  if (axios.isAxiosError<ApiEnvelope<unknown>>(error)) {
    const body: unknown = error.response?.data;
    if (body && typeof body === 'object' && 'error' in body) {
      const apiError = (body as ApiEnvelope<unknown>).error;
      if (apiError?.message) return apiError.message;
    }
    return 'The request could not be completed.';
  }
  return error instanceof Error ? error.message : 'An unexpected error occurred.';
}

export function isUnauthorized(error: unknown): boolean {
  return error instanceof AxiosError && error.response?.status === 401;
}

export async function apiData<T>(config: AxiosRequestConfig): Promise<T> {
  const response = await api.request<ApiEnvelope<T>>(config);
  if (response.data.data === null) throw new Error('The API returned an empty response.');
  return response.data.data;
}
