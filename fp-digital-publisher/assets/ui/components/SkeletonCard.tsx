import * as React from 'react';

let stylesInjected = false;

const ensureSkeletonStyles = () => {
  if (stylesInjected || typeof document === 'undefined') {
    return;
  }

  const style = document.createElement('style');
  style.setAttribute('data-fp-ui-skeleton', '');
  style.innerHTML = `@keyframes fpUiSkeletonShimmer {\n    0% { background-position: -200px 0; }\n    100% { background-position: calc(200px + 100%) 0; }\n  }`;
  document.head.appendChild(style);
  stylesInjected = true;
};

export interface SkeletonCardProps
  extends React.HTMLAttributes<HTMLDivElement> {
  lines?: number;
  showHeader?: boolean;
}

export const SkeletonCard: React.FC<SkeletonCardProps> = ({
  lines = 3,
  showHeader = true,
  className,
  style,
  ...rest
}) => {
  const [prefersReducedMotion, setPrefersReducedMotion] = React.useState(false);

  React.useEffect(() => {
    ensureSkeletonStyles();
  }, []);

  React.useEffect(() => {
    if (typeof window === 'undefined' || !window.matchMedia) {
      return;
    }

    const mediaQuery = window.matchMedia('(prefers-reduced-motion: reduce)');
    const update = () => setPrefersReducedMotion(mediaQuery.matches);
    update();

    if (typeof mediaQuery.addEventListener === 'function') {
      mediaQuery.addEventListener('change', update);
      return () => mediaQuery.removeEventListener('change', update);
    }

    mediaQuery.addListener(update);
    return () => mediaQuery.removeListener(update);
  }, []);

  const mergedClassName = ['fp-ui-skeleton-card', className]
    .filter(Boolean)
    .join(' ');

  const barStyle: React.CSSProperties = {
    width: '100%',
    height: '12px',
    borderRadius: '999px',
    backgroundColor: 'rgba(95, 107, 124, 0.12)',
    backgroundImage:
      'linear-gradient(90deg, rgba(255,255,255,0) 0%, rgba(255,255,255,0.6) 50%, rgba(255,255,255,0) 100%)',
    backgroundSize: '200px 100%',
    backgroundRepeat: 'no-repeat',
    animation: prefersReducedMotion ? 'none' : 'fpUiSkeletonShimmer 1.3s ease-in-out infinite',
  };

  const containerStyle: React.CSSProperties = {
    display: 'flex',
    flexDirection: 'column',
    gap: 'var(--space-2)',
    padding: 'var(--space-4)',
    borderRadius: 'calc(var(--radius) * 0.75)',
    backgroundColor: 'var(--panel)',
    boxShadow: 'var(--shadow-1)',
    border: '1px solid rgba(208, 215, 226, 0.6)',
    ...style,
  };

  return (
    <div {...rest} className={mergedClassName} style={containerStyle}>
      {showHeader ? (
        <div
          style={{
            ...barStyle,
            height: '18px',
            width: '60%',
            marginBottom: 'var(--space-1)',
          }}
        />
      ) : null}
      {Array.from({ length: Math.max(1, lines) }).map((_, index) => (
        <div
          key={index}
          style={{
            ...barStyle,
            width: index === lines - 1 ? '70%' : '100%',
          }}
        />
      ))}
    </div>
  );
};

export default SkeletonCard;
