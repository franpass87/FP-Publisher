import * as React from 'react';

export interface EmptyStateAction {
  label: string;
  onClick?: (event: React.MouseEvent<HTMLElement>) => void;
  href?: string;
  target?: string;
  rel?: string;
}

export interface EmptyStateProps extends React.HTMLAttributes<HTMLDivElement> {
  title: React.ReactNode;
  hint?: React.ReactNode;
  illustration?: React.ReactNode;
  primaryAction?: EmptyStateAction;
  secondaryAction?: EmptyStateAction;
}

const renderAction = (
  action: EmptyStateAction | undefined,
  variant: 'primary' | 'secondary'
) => {
  if (!action) {
    return null;
  }

  const { href, label, onClick, target, rel } = action;
  const computedRel = target === '_blank' && !rel ? 'noreferrer noopener' : rel;
  const commonStyle: React.CSSProperties = {
    display: 'inline-flex',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 'var(--space-1)',
    minHeight: '36px',
    padding: '0 var(--space-4)',
    fontSize: 'var(--font-size-md)',
    fontWeight: 'var(--font-weight-medium)',
    borderRadius: 'calc(var(--radius) / 1.5)',
    border: '1px solid transparent',
    cursor: 'pointer',
    transition: 'background-color 120ms ease, color 120ms ease, border-color 120ms ease',
  };

  const variantStyle: React.CSSProperties =
    variant === 'primary'
      ? {
          backgroundColor: 'var(--primary)',
          borderColor: 'var(--primary)',
          color: '#fff',
        }
      : {
          backgroundColor: 'transparent',
          borderColor: 'var(--border)',
          color: 'var(--text)',
        };

  if (href) {
    return (
      <a
        href={href}
        target={target}
        rel={computedRel}
        onClick={onClick as React.MouseEventHandler<HTMLAnchorElement>}
        style={{ ...commonStyle, ...variantStyle, textDecoration: 'none' }}
        className={`fp-ui-empty-state__action is-${variant}`}
      >
        {label}
      </a>
    );
  }

  return (
    <button
      type="button"
      onClick={onClick as React.MouseEventHandler<HTMLButtonElement>}
      style={{ ...commonStyle, ...variantStyle }}
      className={`fp-ui-empty-state__action is-${variant}`}
    >
      {label}
    </button>
  );
};

export const EmptyState: React.FC<EmptyStateProps> = ({
  title,
  hint,
  illustration,
  primaryAction,
  secondaryAction,
  className,
  style,
  children,
  ...rest
}) => {
  const mergedClassName = ['fp-ui-empty-state', className]
    .filter(Boolean)
    .join(' ');

  const containerStyle: React.CSSProperties = {
    display: 'flex',
    flexDirection: 'column',
    alignItems: 'center',
    justifyContent: 'center',
    textAlign: 'center',
    padding: 'var(--space-6)',
    gap: 'var(--space-3)',
    backgroundColor: 'var(--panel)',
    borderRadius: 'var(--radius)',
    border: '1px dashed rgba(95, 107, 124, 0.25)',
    color: 'var(--text)',
    boxShadow: 'var(--shadow-1)',
    ...style,
  };

  return (
    <div {...rest} className={mergedClassName} style={containerStyle}>
      {illustration}
      <div style={{ display: 'flex', flexDirection: 'column', gap: 'var(--space-2)' }}>
        <h3
          style={{
            fontSize: 'var(--font-size-xl)',
            margin: 0,
          }}
        >
          {title}
        </h3>
        {hint ? (
          <p
            style={{
              margin: 0,
              color: 'var(--muted)',
              fontSize: 'var(--font-size-md)',
            }}
          >
            {hint}
          </p>
        ) : null}
      </div>
      {children}
      {primaryAction || secondaryAction ? (
        <div
          className="fp-ui-empty-state__actions"
          style={{
            display: 'flex',
            gap: 'var(--space-2)',
            flexWrap: 'wrap',
            justifyContent: 'center',
          }}
        >
          {primaryAction ? renderAction(primaryAction, 'primary') : null}
          {secondaryAction ? renderAction(secondaryAction, 'secondary') : null}
        </div>
      ) : null}
    </div>
  );
};

export default EmptyState;
