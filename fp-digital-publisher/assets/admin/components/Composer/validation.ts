/**
 * Composer Validation Logic
 * 
 * Funzioni per validare lo stato del composer
 */

import type { ComposerState, ComposerValidationRules, PreflightInsight } from './types';

export const DEFAULT_VALIDATION_RULES: ComposerValidationRules = {
  titleMinLength: 5,
  captionMinLength: 15,
  captionRecommendedLength: 80,
  titleImpact: 30,
  captionImpact: 30,
  scheduleImpact: 25,
  hashtagsImpact: 10,
};

export interface ValidationResult {
  score: number;
  issues: string[];
  notes: string[];
  insightStatus: Map<string, boolean>;
}

/**
 * Valida lo stato del composer e calcola il punteggio
 */
export function validateComposerState(
  state: ComposerState,
  rules: ComposerValidationRules,
  i18n: {
    titleShort: string;
    captionShort: string;
    captionDetail: string;
    scheduleInvalid: string;
    hashtagsOff: string;
  }
): ValidationResult {
  const issues: string[] = [];
  const notes: string[] = [];
  const insightStatus = new Map<string, boolean>();
  let score = 100;

  // Validazione titolo
  const title = state.title.trim();
  if (title.length < rules.titleMinLength) {
    issues.push(i18n.titleShort);
    insightStatus.set('title', false);
    score -= rules.titleImpact;
  } else {
    insightStatus.set('title', true);
  }

  // Validazione caption
  const caption = state.caption.trim();
  if (caption.length < rules.captionMinLength) {
    issues.push(i18n.captionShort);
    insightStatus.set('caption', false);
    score -= rules.captionImpact;
  } else {
    insightStatus.set('caption', true);
    if (caption.length < rules.captionRecommendedLength) {
      notes.push(i18n.captionDetail);
    }
  }

  // Validazione scheduling
  const scheduledDate = state.scheduledAt ? new Date(state.scheduledAt) : null;
  if (!scheduledDate || Number.isNaN(scheduledDate.getTime()) || scheduledDate.getTime() <= Date.now()) {
    issues.push(i18n.scheduleInvalid);
    insightStatus.set('schedule', false);
    score -= rules.scheduleImpact;
  } else {
    insightStatus.set('schedule', true);
  }

  // Validazione hashtags
  if (state.hashtagsFirst) {
    insightStatus.set('hashtags', true);
  } else {
    insightStatus.set('hashtags', false);
    notes.push(i18n.hashtagsOff);
    score -= rules.hashtagsImpact;
  }

  return {
    score: Math.max(0, Math.min(100, score)),
    issues,
    notes,
    insightStatus,
  };
}

/**
 * Determina il tono del badge preflight basato sul punteggio
 */
export function getPreflightTone(score: number): 'positive' | 'warning' | 'danger' {
  if (score >= 80) return 'positive';
  if (score >= 60) return 'warning';
  return 'danger';
}

/**
 * Calcola lo stato di completamento degli step del composer
 */
export interface StepCompletion {
  content: boolean;
  variants: boolean;
  media: boolean;
  programma: boolean;
  review: boolean;
}

export function calculateStepCompletion(insightStatus: Map<string, boolean>): StepCompletion {
  const hasTitle = insightStatus.get('title') === true;
  const hasCaption = insightStatus.get('caption') === true;
  const hasSchedule = insightStatus.get('schedule') === true;
  const hashtagsReady = insightStatus.get('hashtags') === true;

  return {
    content: hasTitle,
    variants: hasCaption,
    media: hasCaption,
    programma: hasSchedule,
    review: hasSchedule && hashtagsReady,
  };
}

/**
 * Trova il primo step non completato
 */
export function findFirstPendingStep(completion: StepCompletion): string {
  const steps: Array<keyof StepCompletion> = ['content', 'variants', 'media', 'programma', 'review'];
  return steps.find((step) => !completion[step]) ?? 'review';
}

/**
 * Insight di preflight predefiniti
 */
export function createDefaultInsights(i18n: {
  titleLabel: string;
  titleDescription: string;
  captionLabel: string;
  captionDescription: string;
  scheduleLabel: string;
  scheduleDescription: string;
  hashtagsLabel: string;
  hashtagsDescription: string;
}): PreflightInsight[] {
  return [
    {
      id: 'title',
      label: i18n.titleLabel,
      description: i18n.titleDescription,
      impact: 30,
    },
    {
      id: 'caption',
      label: i18n.captionLabel,
      description: i18n.captionDescription,
      impact: 30,
    },
    {
      id: 'schedule',
      label: i18n.scheduleLabel,
      description: i18n.scheduleDescription,
      impact: 25,
    },
    {
      id: 'hashtags',
      label: i18n.hashtagsLabel,
      description: i18n.hashtagsDescription,
      impact: 15,
    },
  ];
}
