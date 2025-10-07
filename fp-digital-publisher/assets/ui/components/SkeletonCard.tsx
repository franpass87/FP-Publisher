import * as React from 'react';
import styles from './SkeletonCard.module.css';


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

  React.useEffect(() => {}, []);

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

  const mergedClassName = [styles.container, 'fp-ui-skeleton-card', className]
    .filter(Boolean)
    .join(' ');

  const barClass = prefersReducedMotion ? undefined : styles.bar;

  const containerStyle: React.CSSProperties = { ...style };

  return (
    <div {...rest} className={mergedClassName} style={containerStyle}>
      {showHeader ? (
        <div className={[barClass, styles.barLg].filter(Boolean).join(' ')} />
      ) : null}
      {Array.from({ length: Math.max(1, lines) }).map((_, index) => (
        <div key={index} className={[barClass, index === lines - 1 ? styles.barShort : undefined].filter(Boolean).join(' ')} />
      ))}
    </div>
  );
};

export default SkeletonCard;
