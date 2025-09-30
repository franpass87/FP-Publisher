import * as React from 'react';
import { __ } from '@wordpress/i18n';

export type DensityMode = 'comfort' | 'compact';

export interface DensityToggleProps extends React.HTMLAttributes<HTMLDivElement> {
  mode: DensityMode;
  onChange: (mode: DensityMode) => void;
  labels?: Partial<Record<DensityMode, string>>;
}

const DEFAULT_LABELS: Record<DensityMode, string> = {
  comfort: __('Comfort', 'fp_publisher'),
  compact: __('Compatta', 'fp_publisher'),
};

export const DensityToggle: React.FC<DensityToggleProps> = ({
  mode,
  onChange,
  labels = DEFAULT_LABELS,
  className,
  ...rest
}) => {
  const handleClick = (nextMode: DensityMode) => {
    if (nextMode === mode) {
      return;
    }
    onChange(nextMode);
  };

  const mergedClassName = ['fp-ui-density-toggle', className]
    .filter(Boolean)
    .join(' ');

  return (
    <div
      {...rest}
      className={mergedClassName}
      role="group"
      aria-label={__('Densità elenco', 'fp_publisher')}
    >
      {(Object.keys(DEFAULT_LABELS) as DensityMode[]).map((key) => (
        <button
          key={key}
          type="button"
          className={['fp-ui-density-toggle__button', mode === key ? 'is-active' : '']
            .filter(Boolean)
            .join(' ')}
          aria-pressed={mode === key}
          onClick={() => handleClick(key)}
        >
          {labels[key] ?? DEFAULT_LABELS[key]}
        </button>
      ))}
    </div>
  );
};

export default DensityToggle;
