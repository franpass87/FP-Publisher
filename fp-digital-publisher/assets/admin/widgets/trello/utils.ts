/**
 * Trello Widget - Utilities
 * Helper functions for Trello integration
 */

import { sanitizeString } from '../../utils/string';
import type { TrelloCredentials } from '../../types';

/**
 * Extract list ID from URL query parameters
 */
function extractListIdFromQuery(value: string): string {
  const fromSearchParams = (input: string): string => {
    if (!input) {
      return '';
    }

    const params = new URLSearchParams(input.startsWith('?') ? input.slice(1) : input);
    const listParam = params.get('list');
    return listParam ? listParam.trim() : '';
  };

  try {
    const url = new URL(value);
    const fromSearch = fromSearchParams(url.search);
    if (fromSearch) {
      return fromSearch;
    }

    if (url.hash) {
      const fromHash = fromSearchParams(url.hash.startsWith('#') ? url.hash.slice(1) : url.hash);
      if (fromHash) {
        return fromHash;
      }
    }

    return '';
  } catch (error) {
    const [beforeHash, ...hashParts] = value.split('#');
    const fromSearch = fromSearchParams(beforeHash.includes('?') ? beforeHash.split('?')[1] : '');
    if (fromSearch) {
      return fromSearch;
    }

    if (hashParts.length > 0) {
      const fromHash = fromSearchParams(hashParts.join('#'));
      if (fromHash) {
        return fromHash;
      }
    }
  }

  return '';
}

/**
 * Resolve Trello list ID from various URL formats
 */
export function resolveTrelloListId(value: string): string {
  const trimmed = value.trim();
  if (trimmed === '') {
    return '';
  }

  const listFromQuery = extractListIdFromQuery(trimmed);
  if (listFromQuery) {
    return listFromQuery;
  }

  const listMatch = trimmed.match(/\/lists?\/([a-zA-Z0-9]+)/i);
  if (listMatch) {
    return listMatch[1];
  }

  const segments = trimmed.split(/[/?#]/).filter((segment) => segment !== '');
  if (segments.length > 0) {
    return segments[segments.length - 1];
  }

  return trimmed;
}

/**
 * Collect Trello credentials from form
 */
export function collectTrelloCredentials(
  form: HTMLFormElement,
  brand: string,
  channel: string,
): TrelloCredentials {
  const apiKey = (form.querySelector<HTMLInputElement>('input[name="api_key"]')?.value ?? '').trim();
  const token = (form.querySelector<HTMLInputElement>('input[name="token"]')?.value ?? '').trim();
  const oauthToken = (form.querySelector<HTMLInputElement>('input[name="oauth_token"]')?.value ?? '').trim();
  const listValue = (form.querySelector<HTMLInputElement>('input[name="list_id"]')?.value ?? '').trim();

  return {
    apiKey,
    token,
    oauthToken,
    listId: resolveTrelloListId(listValue),
    brand: sanitizeString(brand),
    channel,
  };
}

/**
 * Run unit tests for resolveTrelloListId
 */
export function runResolveTrelloListIdChecks(): void {
  const testCases: Array<{ input: string; expected: string }> = [
    {
      input: 'https://trello.com/b/abc123/demo-board?list=64a84efc1234567890abcdef',
      expected: '64a84efc1234567890abcdef',
    },
    {
      input: 'https://trello.com/b/abc123/demo-board#?list=64a84efc1234567890abcdef',
      expected: '64a84efc1234567890abcdef',
    },
    {
      input: 'https://trello.com/lists/64a84efc1234567890abcdef/demo-list',
      expected: '64a84efc1234567890abcdef',
    },
    {
      input: '64a84efc1234567890abcdef',
      expected: '64a84efc1234567890abcdef',
    },
  ];

  const failures = testCases
    .map(({ input, expected }) => ({ input, expected, actual: resolveTrelloListId(input) }))
    .filter((result) => result.actual !== result.expected);

  if (failures.length > 0) {
    // eslint-disable-next-line no-console
    console.warn('resolveTrelloListId checks failed', failures);
  }
}