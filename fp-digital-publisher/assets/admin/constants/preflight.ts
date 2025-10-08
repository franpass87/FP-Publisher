/**
 * Preflight constants
 * Configuration for preflight checks and insights
 */

import { __ } from '@wordpress/i18n';
import { TEXT_DOMAIN } from './config';
import type { PreflightInsight } from '../types';

export const PREFLIGHT_INSIGHTS: PreflightInsight[] = [
  {
    id: 'title',
    label: __('Title', TEXT_DOMAIN),
    description: __('Use a descriptive title to help the team understand the focus of the content.', TEXT_DOMAIN),
    impact: 30,
  },
  {
    id: 'caption',
    label: __('Caption', TEXT_DOMAIN),
    description: __('Complete the caption with call-to-actions and brand references.', TEXT_DOMAIN),
    impact: 30,
  },
  {
    id: 'schedule',
    label: __('Scheduling', TEXT_DOMAIN),
    description: __('Set a future date and time to avoid conflicts with other content.', TEXT_DOMAIN),
    impact: 25,
  },
  {
    id: 'hashtags',
    label: __('Hashtags', TEXT_DOMAIN),
    description: __('Confirm the hashtags in the first comment to increase Instagram reach.', TEXT_DOMAIN),
    impact: 15,
  },
];