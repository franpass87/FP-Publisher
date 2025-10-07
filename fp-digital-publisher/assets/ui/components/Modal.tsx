import * as React from 'react';
import { createPortal } from 'react-dom';
import { __ } from '@wordpress/i18n';
import styles from './Modal.module.css';

export type ModalSize = 'sm' | 'md' | 'lg';

export interface ModalProps {
  isOpen: boolean;
  onDismiss: () => void;
  title?: React.ReactNode;
  description?: React.ReactNode;
  size?: ModalSize;
  closeLabel?: string;
  children: React.ReactNode;
  footer?: React.ReactNode;
}

const FOCUSABLE_SELECTORS =
  'a[href], area[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

const getModalWidth = (size: ModalSize = 'md') => {
  switch (size) {
    case 'sm':
      return '420px';
    case 'lg':
      return '880px';
    case 'md':
    default:
      return '620px';
  }
};

export const Modal: React.FC<ModalProps> = ({
  isOpen,
  onDismiss,
  title,
  description,
  size = 'md',
  closeLabel = __('Close modal', 'fp-publisher'),
  children,
  footer,
}) => {
  const titleId = React.useId();
  const descriptionId = React.useId();
  const overlayRef = React.useRef<HTMLDivElement | null>(null);
  const contentRef = React.useRef<HTMLDivElement | null>(null);
  const lastFocusedElement = React.useRef<HTMLElement | null>(null);
  const [portalElement, setPortalElement] = React.useState<HTMLElement | null>(null);

  React.useEffect(() => {
    if (typeof document === 'undefined') {
      return;
    }

    let container = document.querySelector<HTMLElement>('#fp-ui-modal-host');

    if (!container) {
      container = document.createElement('div');
      container.id = 'fp-ui-modal-host';
      document.body.appendChild(container);
    }

    setPortalElement(container);

    return () => {
      if (container && container.childElementCount === 0) {
        container.remove();
      }
    };
  }, []);

  React.useEffect(() => {
    if (!isOpen || typeof document === 'undefined') {
      return;
    }

    lastFocusedElement.current = document.activeElement as HTMLElement;

    const previouslyOverflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';

    return () => {
      document.body.style.overflow = previouslyOverflow;
      lastFocusedElement.current?.focus?.();
    };
  }, [isOpen]);

  React.useEffect(() => {
    if (!isOpen) {
      return;
    }

    const node = contentRef.current;
    if (!node) {
      return;
    }

    const focusable = node.querySelectorAll<HTMLElement>(FOCUSABLE_SELECTORS);
    const focusTarget = focusable.length > 0 ? focusable[0] : node;

    if (typeof window !== 'undefined' && typeof window.requestAnimationFrame === 'function') {
      window.requestAnimationFrame(() => focusTarget.focus());
    } else {
      focusTarget.focus();
    }
  }, [isOpen]);

  const handleKeyDown = (event: React.KeyboardEvent<HTMLDivElement>) => {
    if (event.key === 'Escape') {
      event.stopPropagation();
      onDismiss();
      return;
    }

    if (event.key === 'Tab') {
      const node = contentRef.current;
      if (!node) {
        return;
      }

      const focusable = Array.from(
        node.querySelectorAll<HTMLElement>(FOCUSABLE_SELECTORS)
      ).filter((el) => !el.hasAttribute('data-focus-guard'));

      if (focusable.length === 0) {
        event.preventDefault();
        node.focus();
        return;
      }

      const first = focusable[0];
      const last = focusable[focusable.length - 1];
      const active = document.activeElement as HTMLElement | null;

      if (event.shiftKey) {
        if (active === first || !active) {
          event.preventDefault();
          last.focus();
        }
      } else if (active === last) {
        event.preventDefault();
        first.focus();
      }
    }
  };

  const handleOverlayClick = (event: React.MouseEvent<HTMLDivElement>) => {
    if (event.target === overlayRef.current) {
      onDismiss();
    }
  };

  if (!isOpen || !portalElement) {
    return null;
  }

  const overlayStyle: React.CSSProperties = {};

  const contentStyle: React.CSSProperties = {
    width: `min(100%, ${getModalWidth(size)})`,
  };

  const closeButtonStyle: React.CSSProperties = {};

  return createPortal(
    <div
      ref={overlayRef}
      role="presentation"
      onMouseDown={handleOverlayClick}
      className={styles.overlay}
      style={overlayStyle}
    >
      <div
        ref={contentRef}
        role="dialog"
        aria-modal="true"
        aria-labelledby={title ? titleId : undefined}
        aria-describedby={description ? descriptionId : undefined}
        tabIndex={-1}
        className={styles.content}
        style={contentStyle}
        onKeyDown={handleKeyDown}
      >
        <button
          type="button"
          onClick={onDismiss}
          aria-label={closeLabel}
          className={styles.closeButton}
          style={closeButtonStyle}
        >
          <span aria-hidden="true">×</span>
        </button>
        {title ? (
          <header
            style={{
              display: 'flex',
              flexDirection: 'column',
              gap: 'var(--space-1)',
            }}
          >
            <h2
              id={titleId}
              style={{
                margin: 0,
                fontSize: 'var(--font-size-xl)',
                fontWeight: 'var(--font-weight-semibold)',
              }}
            >
              {title}
            </h2>
            {description ? (
              <p
                id={descriptionId}
                style={{
                  margin: 0,
                  color: 'var(--muted)',
                  fontSize: 'var(--font-size-md)',
                }}
              >
                {description}
              </p>
            ) : null}
          </header>
        ) : null}
        <div className={styles.body}>
          {children}
        </div>
        {footer ? <footer className={styles.footer}>{footer}</footer> : null}
      </div>
    </div>,
    portalElement
  );
};

export default Modal;
