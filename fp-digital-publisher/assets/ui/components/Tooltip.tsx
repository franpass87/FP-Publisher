import * as React from 'react';

export type TooltipPlacement = 'top' | 'bottom' | 'left' | 'right';

export interface TooltipProps {
  content: React.ReactNode;
  children: React.ReactElement;
  placement?: TooltipPlacement;
  delay?: number;
}

const composeEventHandler = <E extends React.SyntheticEvent>(
  theirHandler: ((event: E) => void) | undefined,
  ourHandler: (event: E) => void
) => (event: E) => {
  if (theirHandler) {
    theirHandler(event);
  }
  if (!event.defaultPrevented) {
    ourHandler(event);
  }
};

export const Tooltip: React.FC<TooltipProps> = ({
  content,
  children,
  placement = 'top',
  delay = 150,
}) => {
  if (!content) {
    return children;
  }

  const tooltipId = React.useId();
  const [open, setOpen] = React.useState(false);
  const timerRef = React.useRef<number | null>(null);

  const clearTimer = () => {
    if (typeof window === 'undefined') {
      timerRef.current = null;
      return;
    }

    if (timerRef.current) {
      window.clearTimeout(timerRef.current);
      timerRef.current = null;
    }
  };

  const show = () => {
    if (open) {
      return;
    }

    if (typeof window === 'undefined') {
      setOpen(true);
      return;
    }

    clearTimer();
    timerRef.current = window.setTimeout(() => setOpen(true), delay);
  };

  const hide = () => {
    clearTimer();
    setOpen(false);
  };

  React.useEffect(() => clearTimer, []);

  const child = React.cloneElement(children, {
    'aria-describedby': open
      ? [children.props['aria-describedby'], tooltipId]
          .filter(Boolean)
          .join(' ')
      : children.props['aria-describedby'],
    onMouseEnter: composeEventHandler(children.props.onMouseEnter, show),
    onMouseLeave: composeEventHandler(children.props.onMouseLeave, hide),
    onFocus: composeEventHandler(children.props.onFocus, show),
    onBlur: composeEventHandler(children.props.onBlur, hide),
  });

  const tooltipStyle: React.CSSProperties = {
    position: 'absolute',
    zIndex: 20,
    padding: '6px 10px',
    borderRadius: '6px',
    backgroundColor: 'rgba(31, 41, 51, 0.92)',
    color: '#fff',
    fontSize: 'var(--font-size-sm)',
    fontWeight: 'var(--font-weight-medium)',
    lineHeight: 1.2,
    maxWidth: '240px',
    boxShadow: 'var(--shadow-1)',
    pointerEvents: 'none',
    opacity: open ? 1 : 0,
    transition: 'opacity 120ms ease, transform 120ms ease',
  };

  const getPositionStyles = (): React.CSSProperties => {
    switch (placement) {
      case 'bottom':
        return {
          top: 'calc(100% + 8px)',
          left: '50%',
          transform: `translateY(${open ? '0' : '-4px'}) translateX(-50%)`,
        };
      case 'left':
        return {
          right: 'calc(100% + 8px)',
          top: '50%',
          transform: `translateX(${open ? '0' : '4px'}) translateY(-50%)`,
        };
      case 'right':
        return {
          left: 'calc(100% + 8px)',
          top: '50%',
          transform: `translateX(${open ? '0' : '-4px'}) translateY(-50%)`,
        };
      case 'top':
      default:
        return {
          bottom: 'calc(100% + 8px)',
          left: '50%',
          transform: `translateY(${open ? '0' : '4px'}) translateX(-50%)`,
        };
    }
  };

  const arrowStyle: React.CSSProperties = {
    position: 'absolute',
    width: '8px',
    height: '8px',
    backgroundColor: 'inherit',
    transform: 'rotate(45deg)',
  };

  const getArrowPosition = (): React.CSSProperties => {
    switch (placement) {
      case 'bottom':
        return { top: '-4px', left: 'calc(50% - 4px)' };
      case 'left':
        return { right: '-4px', top: 'calc(50% - 4px)' };
      case 'right':
        return { left: '-4px', top: 'calc(50% - 4px)' };
      case 'top':
      default:
        return { bottom: '-4px', left: 'calc(50% - 4px)' };
    }
  };

  return (
    <span
      className="fp-ui-tooltip-wrapper"
      style={{ position: 'relative', display: 'inline-flex' }}
    >
      {child}
      <span
        role="tooltip"
        id={tooltipId}
        aria-hidden={!open}
        style={{ ...tooltipStyle, ...getPositionStyles() }}
      >
        <span style={{ ...arrowStyle, ...getArrowPosition() }} />
        <span>{content}</span>
      </span>
    </span>
  );
};

export default Tooltip;
