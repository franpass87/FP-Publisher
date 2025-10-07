import * as React from 'react';
import { __, _x } from '@wordpress/i18n';
import styles from './StatusBadge.module.css';

type StatusKey =
  | 'draft'
  | 'ready'
  | 'approved'
  | 'scheduled'
  | 'published'
  | 'failed'
  | 'retrying';

type Tone = {
  background: string;
  border: string;
  text: string;
};

type StatusConfig = {
  label: string;
  tone: Tone;
};

const STATUS_MAP: Record<StatusKey, StatusConfig> = {
  draft: {
    label: _x('Draft', 'content status', 'fp-publisher'),
    tone: {
      background: 'rgba(95, 107, 124, 0.12)',
      border: 'rgba(95, 107, 124, 0.32)',
      text: 'var(--muted)',
    },
  },
  ready: {
    label: _x('Ready', 'content status', 'fp-publisher'),
    tone: {
      background: 'rgba(59, 130, 246, 0.12)',
      border: 'rgba(59, 130, 246, 0.32)',
      text: 'var(--primary)',
    },
  },
  approved: {
    label: _x('Approved', 'content status', 'fp-publisher'),
    tone: {
      background: 'rgba(5, 150, 105, 0.12)',
      border: 'rgba(5, 150, 105, 0.28)',
      text: 'var(--success)',
    },
  },
  scheduled: {
    label: _x('Scheduled', 'content status', 'fp-publisher'),
    tone: {
      background: 'rgba(37, 99, 235, 0.12)',
      border: 'rgba(37, 99, 235, 0.32)',
      text: 'var(--primary-600)',
    },
  },
  published: {
    label: _x('Published', 'content status', 'fp-publisher'),
    tone: {
      background: 'rgba(5, 150, 105, 0.16)',
      border: 'rgba(5, 150, 105, 0.32)',
      text: 'var(--success)',
    },
  },
  failed: {
    label: _x('Failed', 'content status', 'fp-publisher'),
    tone: {
      background: 'rgba(220, 38, 38, 0.12)',
      border: 'rgba(220, 38, 38, 0.36)',
      text: 'var(--danger)',
    },
  },
  retrying: {
    label: _x('Retry', 'content status', 'fp-publisher'),
    tone: {
      background: 'rgba(217, 119, 6, 0.12)',
      border: 'rgba(217, 119, 6, 0.36)',
      text: 'var(--warning)',
    },
  },
};

export interface StatusBadgeProps extends React.HTMLAttributes<HTMLSpanElement> {
  status: StatusKey;
  children?: React.ReactNode;
}

export const StatusBadge: React.FC<StatusBadgeProps> = ({
  status,
  children,
  className,
  style,
  ...rest
}) => {
  const config = STATUS_MAP[status];

  const mergedClassName = [styles.root, 'fp-ui-status-badge', className]
    .filter(Boolean)
    .join(' ');

  const fallbackLabel = config?.label ?? __(status, 'fp-publisher');

  const badgeStyle: React.CSSProperties = {
    backgroundColor: config?.tone.background,
    color: config?.tone.text,
    borderColor: config?.tone.border,
    ...style,
  };

  return (
    <span
      {...rest}
      className={mergedClassName}
      data-status={status}
      style={badgeStyle}
    >
      {children ?? fallbackLabel}
    </span>
  );
};

export default StatusBadge;
