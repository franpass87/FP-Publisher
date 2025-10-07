import * as React from 'react';
import styles from './EmptyState.module.css';

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
  const commonClass = styles.button;

  const variantClass = variant === 'primary' ? styles.primary : styles.secondary;

  if (href) {
    return (
      <a
        href={href}
        target={target}
        rel={computedRel}
        onClick={onClick as React.MouseEventHandler<HTMLAnchorElement>}
        className={[styles.button, variantClass, `fp-ui-empty-state__action is-${variant}`].join(' ')}
      >
        {label}
      </a>
    );
  }

  return (
    <button
      type="button"
      onClick={onClick as React.MouseEventHandler<HTMLButtonElement>}
      className={[commonClass, variantClass, `fp-ui-empty-state__action is-${variant}`].join(' ')}
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
  const mergedClassName = [styles.container, 'fp-ui-empty-state', className]
    .filter(Boolean)
    .join(' ');

  const containerStyle: React.CSSProperties = { ...style };

  return (
    <div {...rest} className={mergedClassName} style={containerStyle}>
      {illustration}
      <div style={{ display: 'flex', flexDirection: 'column', gap: 'var(--space-2)' }}>
        <h3 className={styles.title}>
          {title}
        </h3>
        {hint ? <p className={styles.hint}>{hint}</p> : null}
      </div>
      {children}
      {primaryAction || secondaryAction ? (
        <div className={[styles.actions, 'fp-ui-empty-state__actions'].join(' ')}>
          {primaryAction ? renderAction(primaryAction, 'primary') : null}
          {secondaryAction ? renderAction(secondaryAction, 'secondary') : null}
        </div>
      ) : null}
    </div>
  );
};

export default EmptyState;
