/**
 * Plan utility functions
 */

import { __, sprintf } from '@wordpress/i18n';
import type { CalendarPlanPayload } from '../types';
import { uniqueList, normalizeStatus, humanizeLabel } from './string';

const TEXT_DOMAIN = 'fp-publisher';

export function getPlanId(plan: CalendarPlanPayload | undefined): number | null {
  if (!plan || typeof plan.id !== 'number' || !Number.isFinite(plan.id)) {
    return null;
  }

  return plan.id;
}

export function resolvePlanTitle(plan: CalendarPlanPayload): string {
  const rawTitle = (plan.title ?? plan.template?.name ?? '').trim();
  if (rawTitle) {
    return rawTitle;
  }

  if (plan.id) {
    return sprintf(__('Plan #%d', TEXT_DOMAIN), plan.id);
  }

  return __('Untitled plan', TEXT_DOMAIN);
}

export function planPrimaryTimestamp(plan: CalendarPlanPayload): number {
  const slots = Array.isArray(plan.slots) ? plan.slots : [];
  const timestamps = slots
    .map((slot) => {
      if (!slot || typeof slot.scheduled_at !== 'string' || slot.scheduled_at === '') {
        return Number.POSITIVE_INFINITY;
      }
      const date = new Date(slot.scheduled_at);
      return Number.isNaN(date.getTime()) ? Number.POSITIVE_INFINITY : date.getTime();
    })
    .filter((timestamp) => Number.isFinite(timestamp))
    .sort((a, b) => a - b);

  return timestamps[0] ?? Number.POSITIVE_INFINITY;
}

export function getPlanChannels(plan: CalendarPlanPayload): string[] {
  if (Array.isArray(plan.channels) && plan.channels.length > 0) {
    return plan.channels.filter((channel): channel is string => typeof channel === 'string' && channel.trim() !== '');
  }

  if (Array.isArray(plan.slots)) {
    const channels = plan.slots
      .map((slot) => (slot && typeof slot.channel === 'string' ? slot.channel : ''))
      .filter((channel) => channel !== '');
    if (channels.length > 0) {
      return channels;
    }
  }

  return [];
}

export function getPlanChannelsLabel(plan: CalendarPlanPayload): string {
  const channels = uniqueList(getPlanChannels(plan).map((channel) => normalizeStatus(channel)));
  if (channels.length === 0) {
    return __('Channels pending', TEXT_DOMAIN);
  }

  return channels.map((channel) => humanizeLabel(channel)).join(', ');
}

export function getPlanScheduleLabel(plan: CalendarPlanPayload): string {
  const timestamp = planPrimaryTimestamp(plan);
  if (!Number.isFinite(timestamp)) {
    return __('Schedule TBD', TEXT_DOMAIN);
  }

  const NEXT_SLOT_TEMPLATE = __('Next slot %s', TEXT_DOMAIN);
  return sprintf(NEXT_SLOT_TEMPLATE, new Date(timestamp).toLocaleString());
}

export function getPlanSummary(plan: CalendarPlanPayload): string {
  const parts = [resolvePlanTitle(plan)];
  const brand = plan.brand ? humanizeLabel(plan.brand) : '';
  if (brand) {
    parts.push(brand);
  }
  const channels = getPlanChannelsLabel(plan);
  if (channels) {
    parts.push(channels);
  }
  const schedule = getPlanScheduleLabel(plan);
  if (schedule) {
    parts.push(schedule);
  }
  return parts.filter(Boolean).join(' · ');
}

export function parsePlanId(value: string | null | undefined): number | null {
  if (!value) {
    return null;
  }

  const parsed = Number(value);
  return Number.isFinite(parsed) && parsed > 0 ? parsed : null;
}

export function normalizePlanStatusValue(status: string | undefined): string {
  return normalizeStatus(status ?? '');
}