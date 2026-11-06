export function Notice({ children, kind = 'error' }: { children: string; kind?: 'error' | 'success' }) {
  return <p className={`notice notice-${kind}`} role={kind === 'error' ? 'alert' : 'status'}>{children}</p>;
}
