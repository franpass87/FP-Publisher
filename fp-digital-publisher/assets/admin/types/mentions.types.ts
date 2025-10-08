/**
 * Mentions types
 * Types for user mentions in comments
 */

export type MentionSuggestion = {
  id: number;
  name: string;
  slug?: string;
  description?: string | null;
};

export type WPUser = {
  id: number;
  name: string;
  slug?: string;
  description?: string | null;
};