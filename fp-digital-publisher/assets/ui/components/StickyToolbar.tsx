import * as React from 'react';

export interface StickyToolbarProps
  extends React.HTMLAttributes<HTMLDivElement> {
  elevation?: 'flat' | 'floating';
}

export const StickyToolbar = React.forwardRef<HTMLDivElement, StickyToolbarProps>(
  ({ children, className, style, elevation = 'flat', ...rest }, ref) => {
    const mergedClassName = ['fp-ui-sticky-toolbar', className]
      .filter(Boolean)
      .join(' ');

    const toolbarStyle: React.CSSProperties = {
      position: 'sticky',
      top: 0,
      zIndex: 10,
      display: 'flex',
      alignItems: 'center',
      justifyContent: 'space-between',
      gap: 'var(--space-3)',
      padding: 'var(--space-3) var(--space-4)',
      backgroundColor: 'var(--panel)',
      backdropFilter: 'blur(12px)',
      borderBottom: '1px solid var(--border)',
      boxShadow: elevation === 'floating' ? 'var(--shadow-1)' : 'none',
      ...style,
    };

    return (
      <div {...rest} ref={ref} className={mergedClassName} style={toolbarStyle}>
        {children}
      </div>
    );
  }
);

StickyToolbar.displayName = 'StickyToolbar';

export default StickyToolbar;
