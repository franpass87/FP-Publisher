import * as React from 'react';
import styles from './StickyToolbar.module.css';

export interface StickyToolbarProps
  extends React.HTMLAttributes<HTMLDivElement> {
  elevation?: 'flat' | 'floating';
}

export const StickyToolbar = React.forwardRef<HTMLDivElement, StickyToolbarProps>(
  ({ children, className, style, elevation = 'flat', ...rest }, ref) => {
    const mergedClassName = [
      styles.root,
      elevation === 'floating' ? styles.floating : undefined,
      'fp-ui-sticky-toolbar',
      className,
    ]
      .filter(Boolean)
      .join(' ');

    const toolbarStyle: React.CSSProperties = { ...style };

    return (
      <div {...rest} ref={ref} className={mergedClassName} style={toolbarStyle}>
        {children}
      </div>
    );
  }
);

StickyToolbar.displayName = 'StickyToolbar';

export default StickyToolbar;
