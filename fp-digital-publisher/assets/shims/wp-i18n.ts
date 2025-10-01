/**
 * Lightweight shim that maps the WordPress wp.i18n globals to ES module exports.
 */

type Translate = (text: string, domain?: string) => string;
type Sprintf = (format: string, ...args: unknown[]) => string;

type I18nApi = {
  __: Translate;
  sprintf: Sprintf;
};

let cached: I18nApi | null = null;

function resolveI18n(): I18nApi {
  if (cached) {
    return cached;
  }

  const root: typeof globalThis & {
    wp?: { i18n?: Partial<I18nApi> };
  } = typeof window !== 'undefined' ? window : (globalThis as typeof globalThis & { wp?: { i18n?: Partial<I18nApi> } });

  const api = root?.wp?.i18n;

  if (!api || typeof api.__ !== 'function' || typeof api.sprintf !== 'function') {
    throw new Error('wp.i18n is not available. Ensure the "wp-i18n" script is enqueued.');
  }

  cached = {
    __: api.__.bind(api),
    sprintf: api.sprintf.bind(api),
  } as I18nApi;

  return cached;
}

export const __: Translate = (text, domain) => resolveI18n().__(text, domain);
export const sprintf: Sprintf = (format, ...args) => resolveI18n().sprintf(format, ...args);
