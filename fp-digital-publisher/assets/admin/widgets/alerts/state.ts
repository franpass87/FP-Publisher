/**
 * Alerts Widget - State
 * State management for alerts widget
 */

import type { AlertTabKey } from '../../constants';

/**
 * Active alert tab
 */
export let activeAlertTab: AlertTabKey = 'empty-week';

/**
 * Alert filters
 */
export let alertBrandFilter = '';
export let alertChannelFilter = '';

/**
 * Set active alert tab
 */
export function setActiveAlertTab(tab: AlertTabKey): void {
  activeAlertTab = tab;
}

/**
 * Set brand filter
 */
export function setAlertBrandFilter(brand: string): void {
  alertBrandFilter = brand;
}

/**
 * Set channel filter
 */
export function setAlertChannelFilter(channel: string): void {
  alertChannelFilter = channel;
}

/**
 * Initialize alert filters
 */
export function initAlertFilters(brand: string, channel: string): void {
  alertBrandFilter = brand;
  alertChannelFilter = channel;
}