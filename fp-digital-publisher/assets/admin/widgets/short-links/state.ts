/**
 * ShortLinks Widget - State
 * State management for short links
 */

import type { ShortLink } from '../../types';

/**
 * Short links collection
 */
export let shortLinks: ShortLink[] = [];

/**
 * Modal state
 */
export let shortLinkModalReturnFocus: HTMLElement | null = null;
export let shortLinkEditingSlug: string | null = null;
export let shortLinkModalKeydownHandler: ((event: KeyboardEvent) => void) | null = null;

/**
 * Active menu
 */
export let activeShortLinkMenu: HTMLButtonElement | null = null;

/**
 * Update short links collection
 */
export function setShortLinks(links: ShortLink[]): void {
  shortLinks = links;
}

/**
 * Set modal return focus
 */
export function setModalReturnFocus(element: HTMLElement | null): void {
  shortLinkModalReturnFocus = element;
}

/**
 * Set editing slug
 */
export function setEditingSlug(slug: string | null): void {
  shortLinkEditingSlug = slug;
}

/**
 * Set modal keydown handler
 */
export function setModalKeydownHandler(handler: ((event: KeyboardEvent) => void) | null): void {
  shortLinkModalKeydownHandler = handler;
}

/**
 * Set active menu
 */
export function setActiveMenu(button: HTMLButtonElement | null): void {
  activeShortLinkMenu = button;
}