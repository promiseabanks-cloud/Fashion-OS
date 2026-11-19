import type { ReactNode } from 'react';

/**
 * Small presentational building blocks shared by the studio views.
 */

export function FullPageMessage({
  eyebrow,
  title,
  detail,
  action,
}: {
  eyebrow?: string;
  title: string;
  detail?: string;
  action?: ReactNode;
}) {
  return (
    <div className="fashionos-gate">
      <div className="fashionos-gate__card" role="status" aria-live="polite">
        {eyebrow ? <p className="fashionos-eyebrow">{eyebrow}</p> : null}
        <h1 className="fashionos-gate__title">{title}</h1>
        {detail ? <p className="fashionos-gate__lead">{detail}</p> : null}
        {action ? <div className="fashionos-actions fashionos-gate__actions">{action}</div> : null}
      </div>
    </div>
  );
}

export function Panel({
  title,
  description,
  action,
  quiet,
  children,
}: {
  title?: string;
  description?: string;
  action?: ReactNode;
  quiet?: boolean;
  children: ReactNode;
}) {
  return (
    <section className={`fashionos-panel${quiet ? ' fashionos-panel--quiet' : ''}`}>
      {title || action ? (
        <div className="fashionos-panel__header">
          <div>
            {title ? <h2 className="fashionos-panel__title">{title}</h2> : null}
            {description ? <p className="fashionos-panel__description">{description}</p> : null}
          </div>
          {action}
        </div>
      ) : null}
      {children}
    </section>
  );
}

export function Alert({ children }: { children: ReactNode }) {
  return (
    <p className="fashionos-alert" role="alert">
      {children}
    </p>
  );
}

export function Loading({ label = 'Loading…' }: { label?: string }) {
  return (
    <p className="fashionos-loading" role="status">
      {label}
    </p>
  );
}

export function EmptyState({ title, detail }: { title: string; detail?: string }) {
  return (
    <div className="fashionos-empty">
      <p className="fashionos-empty__title">{title}</p>
      {detail ? <p className="fashionos-empty__detail">{detail}</p> : null}
    </div>
  );
}

/**
 * Format a UTC `DATETIME` from the API for display, with a readable fallback.
 */
export function formatDate(value: string): string {
  if (!value) {
    return '—';
  }

  const date = new Date(value.includes('T') ? value : `${value.replace(' ', 'T')}Z`);

  if (Number.isNaN(date.getTime())) {
    return value;
  }

  return new Intl.DateTimeFormat(undefined, {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(date);
}
