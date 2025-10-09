/**
 * Composer Component Types
 * 
 * Tipi TypeScript per il componente Composer estratti dal file monolitico
 */

export interface ComposerState {
  title: string;
  caption: string;
  scheduledAt: string;
  hashtagsFirst: boolean;
  issues: string[];
  notes: string[];
  score: number;
}

export interface PreflightInsight {
  id: string;
  label: string;
  description: string;
  impact: number;
}

export type PreflightTone = 'positive' | 'warning' | 'danger';

export interface ComposerValidationRules {
  titleMinLength: number;
  captionMinLength: number;
  captionRecommendedLength: number;
  titleImpact: number;
  captionImpact: number;
  scheduleImpact: number;
  hashtagsImpact: number;
}

export interface ComposerI18n {
  header: string;
  subtitle: string;
  stepperLabel: string;
  steps: {
    content: string;
    variants: string;
    media: string;
    schedule: string;
    review: string;
  };
  fields: {
    title: {
      label: string;
      placeholder: string;
    };
    caption: {
      label: string;
      placeholder: string;
      hint: string;
    };
    schedule: {
      label: string;
    };
  };
  hashtagToggle: {
    label: string;
    description: string;
    previewTitle: string;
    previewBody: string;
  };
  actions: {
    saveDraft: string;
    submit: string;
  };
  feedback: {
    blocking: string;
    scheduled: string;
    fallbackDate: string;
    issuesPrefix: string;
    noIssues: string;
    draftSaved: string;
  };
  validation: {
    titleShort: string;
    captionShort: string;
    captionDetail: string;
    scheduleInvalid: string;
    hashtagsOff: string;
  };
  preflight: {
    chipLabel: string;
    modalTitle: string;
  };
  common: {
    close: string;
  };
}

export interface ComposerCallbacks {
  onSubmit?: (state: ComposerState) => void | Promise<void>;
  onSaveDraft?: (state: ComposerState) => void | Promise<void>;
  onValidate?: (state: ComposerState) => void;
}
