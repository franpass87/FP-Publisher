import * as React from 'react';
import { __ } from '@wordpress/i18n';

type ToastIntent = 'neutral' | 'success' | 'warning' | 'danger';

type ToastAction = {
  label: string;
  onClick: (event: React.MouseEvent<HTMLButtonElement>) => void;
};

type ToastInput = {
  id?: string;
  title: React.ReactNode;
  description?: React.ReactNode;
  intent?: ToastIntent;
  duration?: number;
  action?: ToastAction;
};

type ToastRecord = Required<Pick<ToastInput, 'title'>> &
  Omit<ToastInput, 'title'> & {
    id: string;
    createdAt: number;
  };

type ToastEvent =
  | { type: 'add'; toast: ToastRecord }
  | { type: 'remove'; id: string };

const listeners = new Set<(event: ToastEvent) => void>();

const DEFAULT_DURATION = 5000;

const generateId = () =>
  Math.random().toString(36).slice(2) + Date.now().toString(36);

export const pushToast = (input: ToastInput) => {
  const toast: ToastRecord = {
    id: input.id ?? generateId(),
    title: input.title,
    description: input.description,
    intent: input.intent ?? 'neutral',
    duration: input.duration ?? DEFAULT_DURATION,
    action: input.action,
    createdAt: Date.now(),
  };

  listeners.forEach((listener) => listener({ type: 'add', toast }));

  if (toast.duration && toast.duration > 0 && typeof window !== 'undefined') {
    window.setTimeout(() => dismissToast(toast.id), toast.duration);
  }

  return toast.id;
};

export const dismissToast = (id: string) => {
  listeners.forEach((listener) => listener({ type: 'remove', id }));
};

export interface ToastHostProps {
  placement?: 'top-start' | 'top-end' | 'bottom-start' | 'bottom-end';
  maxToasts?: number;
}

const getPlacementStyles = (
  placement: ToastHostProps['placement']
): React.CSSProperties => {
  const base: React.CSSProperties = {
    position: 'fixed',
    zIndex: 1200,
    display: 'flex',
    flexDirection: 'column',
    gap: 'var(--space-2)',
    pointerEvents: 'none',
  };

  switch (placement) {
    case 'top-start':
      return { ...base, top: 'var(--space-5)', left: 'var(--space-5)', alignItems: 'flex-start' };
    case 'bottom-start':
      return { ...base, bottom: 'var(--space-5)', left: 'var(--space-5)', alignItems: 'flex-start' };
    case 'bottom-end':
      return { ...base, bottom: 'var(--space-5)', right: 'var(--space-5)', alignItems: 'flex-end' };
    case 'top-end':
    default:
      return { ...base, top: 'var(--space-5)', right: 'var(--space-5)', alignItems: 'flex-end' };
  }
};

const getToastColors = (intent: ToastIntent = 'neutral') => {
  switch (intent) {
    case 'success':
      return {
        background: 'rgba(5, 150, 105, 0.16)',
        border: 'rgba(5, 150, 105, 0.45)',
        text: 'var(--success)',
      };
    case 'warning':
      return {
        background: 'rgba(217, 119, 6, 0.16)',
        border: 'rgba(217, 119, 6, 0.45)',
        text: 'var(--warning)',
      };
    case 'danger':
      return {
        background: 'rgba(220, 38, 38, 0.16)',
        border: 'rgba(220, 38, 38, 0.45)',
        text: 'var(--danger)',
      };
    case 'neutral':
    default:
      return {
        background: 'rgba(59, 130, 246, 0.1)',
        border: 'rgba(37, 99, 235, 0.3)',
        text: 'var(--text)',
      };
  }
};

const emptyStateText = __('No notifications right now.', 'fp-publisher');

export const ToastHost: React.FC<ToastHostProps> = ({
  placement = 'top-end',
  maxToasts = 4,
}) => {
  const [toasts, setToasts] = React.useState<ToastRecord[]>([]);

  React.useEffect(() => {
    const listener = (event: ToastEvent) => {
      setToasts((prev) => {
        if (event.type === 'add') {
          const withoutDuplicate = prev.filter((toast) => toast.id !== event.toast.id);
          const next = [event.toast, ...withoutDuplicate]
            .sort((a, b) => b.createdAt - a.createdAt)
            .slice(0, maxToasts);
          return next;
        }

        return prev.filter((toast) => toast.id !== event.id);
      });
    };

    listeners.add(listener);
    return () => {
      listeners.delete(listener);
    };
  }, [maxToasts]);

  const hostStyle = React.useMemo(
    () => getPlacementStyles(placement),
    [placement]
  );

  return (
    <div
      role="region"
      aria-live="polite"
      aria-label={__('Notifications', 'fp-publisher')}
      className="fp-ui-toast-host"
      style={hostStyle}
    >
      {toasts.length === 0 ? (
        <span
          style={{
            fontSize: 'var(--font-size-sm)',
            color: 'var(--muted)',
            pointerEvents: 'none',
          }}
        >
          {emptyStateText}
        </span>
      ) : null}
      {toasts.map((toast) => {
        const colors = getToastColors(toast.intent ?? 'neutral');
        return (
          <article
            key={toast.id}
            className="fp-ui-toast"
            role="status"
            style={{
              minWidth: '260px',
              maxWidth: '360px',
              backgroundColor: colors.background,
              border: `1px solid ${colors.border}`,
              color: colors.text,
              borderRadius: 'calc(var(--radius) / 1.5)',
              boxShadow: 'var(--shadow-1)',
              padding: 'var(--space-3)',
              pointerEvents: 'auto',
              display: 'grid',
              gap: 'var(--space-2)',
            }}
          >
            <div
              style={{
                display: 'flex',
                justifyContent: 'space-between',
                alignItems: 'flex-start',
                gap: 'var(--space-2)',
              }}
            >
              <div style={{ display: 'grid', gap: 'var(--space-1)' }}>
                <strong style={{ fontSize: 'var(--font-size-md)' }}>
                  {toast.title}
                </strong>
                {toast.description ? (
                  <span style={{ fontSize: 'var(--font-size-sm)', color: 'var(--muted)' }}>
                    {toast.description}
                  </span>
                ) : null}
              </div>
              <button
                type="button"
                onClick={() => dismissToast(toast.id)}
                aria-label={__('Close notification', 'fp-publisher')}
                style={{
                  background: 'transparent',
                  border: 'none',
                  color: colors.text,
                  cursor: 'pointer',
                  fontSize: 'var(--font-size-md)',
                  lineHeight: 1,
                }}
              >
                ×
              </button>
            </div>
            {toast.action ? (
              <div>
                <button
                  type="button"
                  onClick={(event) => {
                    toast.action?.onClick(event);
                    dismissToast(toast.id);
                  }}
                  style={{
                    backgroundColor: 'var(--primary)',
                    border: '1px solid var(--primary)',
                    color: '#fff',
                    padding: '6px 12px',
                    borderRadius: '999px',
                    fontSize: 'var(--font-size-sm)',
                    cursor: 'pointer',
                  }}
                >
                  {toast.action.label}
                </button>
              </div>
            ) : null}
          </article>
        );
      })}
    </div>
  );
};

export default ToastHost;

export type { ToastIntent, ToastAction, ToastInput, ToastRecord };
