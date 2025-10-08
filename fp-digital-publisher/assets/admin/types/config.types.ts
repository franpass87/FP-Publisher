/**
 * Configuration types
 * Types related to application bootstrap and configuration
 */

export interface BootConfig {
  restBase: string;
  nonce: string;
  version: string;
  brand?: string;
  brands?: string[];
  channels?: string[];
}

export type AdminWindow = Window & {
  fpPublisherAdmin?: BootConfig;
};