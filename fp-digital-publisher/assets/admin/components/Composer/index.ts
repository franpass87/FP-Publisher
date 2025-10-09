/**
 * Composer Component - Barrel Export
 * 
 * Punto di accesso unificato per il componente Composer
 */

// Types
export type {
  ComposerState,
  PreflightInsight,
  PreflightTone,
  ComposerValidationRules,
  ComposerI18n,
  ComposerCallbacks,
} from './types';

// Validation
export {
  validateComposerState,
  getPreflightTone,
  calculateStepCompletion,
  findFirstPendingStep,
  createDefaultInsights,
  DEFAULT_VALIDATION_RULES,
  type ValidationResult,
  type StepCompletion,
} from './validation';

// State Management
export {
  ComposerStateManager,
  createComposerStateManager,
  getComposerStateManager,
} from './ComposerState';

// Renderer
export {
  renderComposer,
  updatePreflightModal,
  updatePreflightChip,
  updateStepper,
  updateIssuesDisplay,
  updateSubmitButton,
  updateHashtagPreview,
  showFeedback,
  clearFeedback,
} from './ComposerRenderer';
