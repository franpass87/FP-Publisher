import * as React from 'react';

export type IconName = 'grip' | 'calendar' | 'clock';

const ICON_PATHS: Record<IconName, { viewBox: string; paths: string[] }> = {
  grip: {
    viewBox: '0 0 20 20',
    paths: [
      'M5 4.25a1.25 1.25 0 1 1 0 2.5 1.25 1.25 0 0 1 0-2.5zm5 0a1.25 1.25 0 1 1 0 2.5 1.25 1.25 0 0 1 0-2.5zm5 0a1.25 1.25 0 1 1 0 2.5 1.25 1.25 0 0 1 0-2.5z',
      'M5 8.75A1.25 1.25 0 1 1 5 11a1.25 1.25 0 0 1 0-2.5zm5 0A1.25 1.25 0 1 1 10 11a1.25 1.25 0 0 1 0-2.5zm5 0A1.25 1.25 0 1 1 15 11a1.25 1.25 0 0 1 0-2.5z',
      'M5 13.25a1.25 1.25 0 1 1 0 2.5 1.25 1.25 0 0 1 0-2.5zm5 0a1.25 1.25 0 1 1 0 2.5 1.25 1.25 0 0 1 0-2.5zm5 0a1.25 1.25 0 1 1 0 2.5 1.25 1.25 0 0 1 0-2.5z',
    ],
  },
  calendar: {
    viewBox: '0 0 24 24',
    paths: [
      'M7 2a1 1 0 0 0-1 1v1H5a3 3 0 0 0-3 3v12a3 3 0 0 0 3 3h14a3 3 0 0 0 3-3V7a3 3 0 0 0-3-3h-1V3a1 1 0 1 0-2 0v1H8V3a1 1 0 0 0-1-1zm12 6H5v11a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1z',
    ],
  },
  clock: {
    viewBox: '0 0 24 24',
    paths: [
      'M12 2a10 10 0 1 0 10 10A10.011 10.011 0 0 0 12 2zm0 18a8 8 0 1 1 8-8 8.009 8.009 0 0 1-8 8z',
      'M12.75 7a.75.75 0 0 0-1.5 0v5.25a.75.75 0 0 0 .44.68l3.5 1.75a.75.75 0 1 0 .66-1.35L12.75 11.8z',
    ],
  },
};

export interface IconProps extends React.SVGAttributes<SVGElement> {
  name: IconName;
  size?: number;
  title?: string;
}

export const Icon: React.FC<IconProps> = ({
  name,
  size = 16,
  title,
  role = 'img',
  focusable = false,
  ...rest
}) => {
  const definition = ICON_PATHS[name];
  if (!definition) {
    return null;
  }

  const { viewBox, paths } = definition;
  const ariaHidden = rest['aria-hidden'] ?? (title ? undefined : true);

  return (
    <svg
      {...rest}
      role={role}
      aria-hidden={ariaHidden}
      width={size}
      height={size}
      viewBox={viewBox}
      focusable={focusable}
      xmlns="http://www.w3.org/2000/svg"
      fill="currentColor"
    >
      {title ? <title>{title}</title> : null}
      {paths.map((d, index) => (
        <path key={index} d={d} fillRule="evenodd" clipRule="evenodd" />
      ))}
    </svg>
  );
};

export default Icon;
