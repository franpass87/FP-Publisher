/**
 * Composer types
 * Types for the content composer component and preflight checks
 */

export type ComposerState = {
  title: string;
  caption: string;
  scheduledAt: string;
  hashtagsFirst: boolean;
  issues: string[];
  notes: string[];
  score: number;
};

export type PreflightInsight = {
  id: string;
  label: string;
  description: string;
  impact: number;
};

export type Suggestion = {
  datetime: string;
  score: number;
  reason: string;
};