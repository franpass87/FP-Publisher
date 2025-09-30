import * as React from 'react';
import { __, sprintf } from '@wordpress/i18n';

import StatusBadge from '../../components/StatusBadge';
import StickyToolbar from '../../components/StickyToolbar';
import EmptyState from '../../components/EmptyState';
import SkeletonCard from '../../components/SkeletonCard';
import Tooltip from '../../components/Tooltip';
import Modal from '../../components/Modal';
import DensityToggle, {
  type DensityMode,
} from '../../components/DensityToggle';
import Icon from '../../components/Icon';
import ToastHost, { pushToast } from '../../components/ToastHost';

const STATUS_KEYS: Array<React.ComponentProps<typeof StatusBadge>['status']> = [
  'draft',
  'ready',
  'approved',
  'scheduled',
  'published',
  'failed',
  'retrying',
];

const cardStyle: React.CSSProperties = {
  backgroundColor: 'var(--panel)',
  borderRadius: 'var(--radius)',
  border: '1px solid rgba(95, 107, 124, 0.14)',
  boxShadow: 'var(--shadow-1)',
  padding: 'var(--space-5)',
  display: 'grid',
  gap: 'var(--space-4)',
};

const toolbarButtonStyle: React.CSSProperties = {
  border: '1px solid var(--border)',
  background: 'var(--panel)',
  borderRadius: 'calc(var(--radius) / 2)',
  padding: '0 var(--space-3)',
  minHeight: '36px',
  display: 'inline-flex',
  alignItems: 'center',
  gap: 'var(--space-1)',
  fontSize: 'var(--font-size-md)',
  fontWeight: 'var(--font-weight-medium)',
};

const badgeWrapStyle: React.CSSProperties = {
  display: 'flex',
  flexWrap: 'wrap',
  gap: 'var(--space-2)',
};

const skeletonRowStyle: React.CSSProperties = {
  display: 'grid',
  gap: 'var(--space-3)',
  gridTemplateColumns: 'repeat(auto-fit, minmax(220px, 1fr))',
};

const tokenPreviewStyle: React.CSSProperties = {
  display: 'grid',
  gap: 'var(--space-3)',
  gridTemplateColumns: 'repeat(auto-fit, minmax(180px, 1fr))',
};

const swatchStyle: React.CSSProperties = {
  borderRadius: 'calc(var(--radius) / 1.8)',
  border: '1px solid rgba(95, 107, 124, 0.18)',
  boxShadow: 'var(--shadow-1)',
  padding: 'var(--space-3)',
  display: 'flex',
  flexDirection: 'column',
  gap: 'var(--space-2)',
};

const UiShowcase: React.FC = () => {
  const [densityMode, setDensityMode] = React.useState<DensityMode>('comfort');
  const [isModalOpen, setIsModalOpen] = React.useState(false);

  const heading = __('FP Digital Publisher component showcase', 'fp-publisher');
  const intro = __(
    'Quick overview of the shared design tokens and reusable components of the admin SPA.',
    'fp-publisher'
  );

  const handleToast = (intent: 'neutral' | 'success' | 'warning' | 'danger') => {
    pushToast({
      title: sprintf(__('Toast %s', 'fp-publisher'), intent),
      description: __('This is an example of a transient notification with aria-live announcement.', 'fp-publisher'),
      intent,
      action:
        intent === 'danger'
          ? {
              label: __('Cancel', 'fp-publisher'),
              onClick: () => {
                pushToast({
                  title: __('Action cancelled', 'fp-publisher'),
                  intent: 'neutral',
                });
              },
            }
          : undefined,
    });
  };

  return (
    <div
      className="fp-ui-showcase"
      style={{
        minHeight: '100vh',
        background:
          'linear-gradient(180deg, rgba(238,242,255,0.55) 0%, rgba(244,247,252,0.9) 100%)',
        padding: 'var(--space-6)',
        display: 'grid',
        gap: 'var(--space-6)',
      }}
    >
      <ToastHost placement="top-end" />
      <StickyToolbar elevation="floating">
        <div style={{ display: 'flex', flexDirection: 'column', gap: '4px' }}>
          <strong style={{ fontSize: 'var(--font-size-xl)' }}>{heading}</strong>
          <span style={{ color: 'var(--muted)', fontSize: 'var(--font-size-md)' }}>
            {intro}
          </span>
        </div>
        <div style={{ display: 'flex', gap: 'var(--space-2)', alignItems: 'center' }}>
          <DensityToggle mode={densityMode} onChange={setDensityMode} />
          <Tooltip content={__('Open showcase modal', 'fp-publisher')}>
            <button
              type="button"
              style={toolbarButtonStyle}
              onClick={() => setIsModalOpen(true)}
            >
              <Icon name="calendar" size={16} aria-hidden />
              <span>{__('Show modal', 'fp-publisher')}</span>
            </button>
          </Tooltip>
          <Tooltip content={__('Send a sample toast', 'fp-publisher')}>
            <button
              type="button"
              style={{ ...toolbarButtonStyle, backgroundColor: 'var(--primary)', color: '#fff', borderColor: 'var(--primary)' }}
              onClick={() => handleToast('success')}
            >
              <Icon name="clock" size={16} aria-hidden />
              <span>{__('Quick toast', 'fp-publisher')}</span>
            </button>
          </Tooltip>
        </div>
      </StickyToolbar>

      <section style={cardStyle} aria-labelledby="tokens-title">
        <div style={{ display: 'grid', gap: 'var(--space-1)' }}>
          <h2 id="tokens-title" style={{ margin: 0, fontSize: 'var(--font-size-xl)' }}>
            {__('Design tokens', 'fp-publisher')}
          </h2>
          <p style={{ margin: 0, color: 'var(--muted)' }}>
            {__('Palette, spacing, and typography share the same CSS source (`tokens.css`).', 'fp-publisher')}
          </p>
        </div>
        <div style={tokenPreviewStyle}>
          {[
            { label: 'Primary', token: 'var(--primary)' },
            { label: 'Panel', token: 'var(--panel)' },
            { label: 'Success', token: 'var(--success)' },
            { label: 'Warning', token: 'var(--warning)' },
            { label: 'Danger', token: 'var(--danger)' },
            { label: 'Border', token: 'var(--border)' },
          ].map(({ label, token }) => (
            <div key={label} style={swatchStyle}>
              <span style={{ fontWeight: 'var(--font-weight-semibold)' }}>{label}</span>
              <span
                style={{
                  height: '36px',
                  borderRadius: 'calc(var(--radius) / 2)',
                  backgroundColor: token,
                  border: '1px solid rgba(15, 23, 42, 0.05)',
                }}
                aria-hidden
              />
              <code style={{ fontSize: 'var(--font-size-sm)', color: 'var(--muted)' }}>{token}</code>
            </div>
          ))}
        </div>
      </section>

      <section style={cardStyle} aria-labelledby="badge-title">
        <div style={{ display: 'grid', gap: 'var(--space-1)' }}>
          <h2 id="badge-title" style={{ margin: 0, fontSize: 'var(--font-size-xl)' }}>
            {__('Status badges', 'fp-publisher')}
          </h2>
          <p style={{ margin: 0, color: 'var(--muted)' }}>
            {__('Each status applies consistent tones and readable text.', 'fp-publisher')}
          </p>
        </div>
        <div style={badgeWrapStyle}>
          {STATUS_KEYS.map((status) => (
            <StatusBadge key={status} status={status} />
          ))}
        </div>
      </section>

      <section style={cardStyle} aria-labelledby="layout-title">
        <div style={{ display: 'grid', gap: 'var(--space-1)' }}>
          <h2 id="layout-title" style={{ margin: 0, fontSize: 'var(--font-size-xl)' }}>
            {__('Layouts and empty states', 'fp-publisher')}
          </h2>
          <p style={{ margin: 0, color: 'var(--muted)' }}>
            {__('Card, skeleton, and empty state components cover loading states and content gaps.', 'fp-publisher')}
          </p>
        </div>
        <div style={{ display: 'grid', gap: 'var(--space-4)' }}>
          <div style={skeletonRowStyle}>
            {Array.from({ length: 3 }).map((_, index) => (
              <SkeletonCard key={index} lines={index === 0 ? 4 : 3} showHeader={index !== 2} />
            ))}
          </div>
          <EmptyState
            title={__('No scheduled content', 'fp-publisher')}
            hint={__('Import a Trello board or create a new post from the Composer.', 'fp-publisher')}
            primaryAction={{
              label: __('Open Composer', 'fp-publisher'),
              onClick: () => handleToast('neutral'),
            }}
            secondaryAction={{
              label: __('Import from Trello', 'fp-publisher'),
              onClick: () => handleToast('warning'),
            }}
          />
        </div>
      </section>

      <section style={cardStyle} aria-labelledby="toast-title">
        <div style={{ display: 'grid', gap: 'var(--space-1)' }}>
          <h2 id="toast-title" style={{ margin: 0, fontSize: 'var(--font-size-xl)' }}>
            {__('Contextual notifications', 'fp-publisher')}
          </h2>
          <p style={{ margin: 0, color: 'var(--muted)' }}>
            {__('The ToastHost delivers polite notifications with optional actions.', 'fp-publisher')}
          </p>
        </div>
        <div style={{ display: 'flex', gap: 'var(--space-2)', flexWrap: 'wrap' }}>
          {(['neutral', 'success', 'warning', 'danger'] as const).map((intent) => (
            <button
              key={intent}
              type="button"
              style={{
                ...toolbarButtonStyle,
                backgroundColor: intent === 'neutral' ? 'var(--panel)' : 'var(--primary)',
                color: intent === 'neutral' ? 'var(--text)' : '#fff',
                borderColor: intent === 'neutral' ? 'var(--border)' : 'var(--primary)',
              }}
              onClick={() => handleToast(intent)}
            >
              {__('Toast', 'fp-publisher')} {intent}
            </button>
          ))}
        </div>
      </section>

      <Modal
        isOpen={isModalOpen}
        onDismiss={() => setIsModalOpen(false)}
        title={__('Demo modal', 'fp-publisher')}
        description={__('Modal with focus trap, ESC handling, and overlay close.', 'fp-publisher')}
        footer={
          <button
            type="button"
            onClick={() => setIsModalOpen(false)}
            style={{
              ...toolbarButtonStyle,
              backgroundColor: 'var(--primary)',
              color: '#fff',
              borderColor: 'var(--primary)',
            }}
          >
            {__('Close', 'fp-publisher')}
          </button>
        }
      >
        <p style={{ margin: 0, color: 'var(--text)' }}>
          {__('This modal showcases medium sizing with accessible focus management.', 'fp-publisher')}
        </p>
        <p style={{ margin: 0, color: 'var(--muted)', fontSize: 'var(--font-size-sm)' }}>
          {__('Use ESC or click the overlay to close.', 'fp-publisher')}
        </p>
      </Modal>

      <section style={cardStyle} aria-labelledby="density-title">
        <div style={{ display: 'grid', gap: 'var(--space-1)' }}>
          <h2 id="density-title" style={{ margin: 0, fontSize: 'var(--font-size-xl)' }}>
            {__('List density preview', 'fp-publisher')}
          </h2>
          <p style={{ margin: 0, color: 'var(--muted)' }}>
            {__('The toggle applies density classes to lists and tables to accommodate busy calendars.', 'fp-publisher')}
          </p>
        </div>
        <div
          style={{
            display: 'grid',
            gap: 'var(--space-2)',
          }}
        >
          <DensityToggle mode={densityMode} onChange={setDensityMode} />
          <div
            role="list"
            data-density={densityMode}
            style={{
              display: 'grid',
              gap: densityMode === 'compact' ? 'var(--space-1)' : 'var(--space-2)',
            }}
          >
            {[
              __('Editorial calendar', 'fp-publisher'),
              __('Composer', 'fp-publisher'),
              __('Approvals', 'fp-publisher'),
            ].map((label) => (
              <div
                key={label}
                role="listitem"
                style={{
                  border: '1px solid var(--border)',
                  borderRadius: 'calc(var(--radius) / 2)',
                  padding:
                    densityMode === 'compact'
                      ? 'var(--space-2) var(--space-3)'
                      : 'var(--space-3) var(--space-4)',
                  backgroundColor: 'var(--panel)',
                  display: 'flex',
                  alignItems: 'center',
                  justifyContent: 'space-between',
                  gap: 'var(--space-2)',
                }}
              >
                <div style={{ display: 'flex', alignItems: 'center', gap: 'var(--space-2)' }}>
                  <Icon name="grip" aria-hidden />
                  <span>{label}</span>
                </div>
                <StatusBadge status="ready" />
              </div>
            ))}
          </div>
        </div>
      </section>
    </div>
  );
};

export default UiShowcase;
