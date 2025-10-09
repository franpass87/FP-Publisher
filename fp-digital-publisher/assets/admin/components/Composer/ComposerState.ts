/**
 * Composer State Management
 * 
 * Gestisce lo stato del composer con validazione reattiva
 */

import type { ComposerState, ComposerValidationRules } from './types';
import { 
  validateComposerState, 
  DEFAULT_VALIDATION_RULES,
  type ValidationResult 
} from './validation';

export class ComposerStateManager {
  private state: ComposerState;
  private rules: ComposerValidationRules;
  private listeners: Array<(state: ComposerState, validation: ValidationResult) => void> = [];

  constructor(initialState?: Partial<ComposerState>, rules?: ComposerValidationRules) {
    this.state = {
      title: '',
      caption: '',
      scheduledAt: '',
      hashtagsFirst: false,
      issues: [],
      notes: [],
      score: 100,
      ...initialState,
    };
    this.rules = rules ?? DEFAULT_VALIDATION_RULES;
  }

  /**
   * Ottiene lo stato corrente
   */
  getState(): Readonly<ComposerState> {
    return { ...this.state };
  }

  /**
   * Aggiorna lo stato e valida
   */
  updateState(
    partial: Partial<ComposerState>,
    i18n: {
      titleShort: string;
      captionShort: string;
      captionDetail: string;
      scheduleInvalid: string;
      hashtagsOff: string;
    }
  ): ValidationResult {
    this.state = { ...this.state, ...partial };
    
    const validation = validateComposerState(this.state, this.rules, i18n);
    
    this.state.score = validation.score;
    this.state.issues = validation.issues;
    this.state.notes = validation.notes;
    
    this.notifyListeners(validation);
    
    return validation;
  }

  /**
   * Resetta lo stato
   */
  reset(): void {
    this.state = {
      title: '',
      caption: '',
      scheduledAt: '',
      hashtagsFirst: false,
      issues: [],
      notes: [],
      score: 100,
    };
  }

  /**
   * Verifica se lo stato è valido per il submit
   */
  isValid(): boolean {
    return this.state.issues.length === 0;
  }

  /**
   * Ottiene il punteggio corrente
   */
  getScore(): number {
    return this.state.score;
  }

  /**
   * Registra un listener per i cambiamenti di stato
   */
  onChange(listener: (state: ComposerState, validation: ValidationResult) => void): () => void {
    this.listeners.push(listener);
    
    // Ritorna funzione per rimuovere il listener
    return () => {
      const index = this.listeners.indexOf(listener);
      if (index > -1) {
        this.listeners.splice(index, 1);
      }
    };
  }

  /**
   * Notifica tutti i listener
   */
  private notifyListeners(validation: ValidationResult): void {
    const stateCopy = this.getState();
    this.listeners.forEach((listener) => {
      listener(stateCopy, validation);
    });
  }
}

// Factory singleton
let stateManagerInstance: ComposerStateManager | null = null;

export function createComposerStateManager(
  initialState?: Partial<ComposerState>,
  rules?: ComposerValidationRules
): ComposerStateManager {
  stateManagerInstance = new ComposerStateManager(initialState, rules);
  return stateManagerInstance;
}

export function getComposerStateManager(): ComposerStateManager {
  if (!stateManagerInstance) {
    throw new Error('ComposerStateManager not initialized. Call createComposerStateManager first.');
  }
  return stateManagerInstance;
}
