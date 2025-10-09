/**
 * Comments Utility Functions
 * 
 * Funzioni helper per il componente Comments
 */

import type { MentionSuggestion } from './types';

/**
 * Formatta il corpo del commento convertendo mentions in link
 */
export function formatCommentBody(body: string): string {
  if (!body || typeof body !== 'string') {
    return '';
  }

  // Escapa HTML prima
  let formatted = escapeHtml(body);

  // Converti @username in link (se presenti)
  formatted = formatted.replace(
    /@(\w+)/g,
    '<strong class="fp-comment-mention">@$1</strong>'
  );

  // Converti newlines in <br>
  formatted = formatted.replace(/\n/g, '<br>');

  return formatted;
}

/**
 * Escapa HTML per prevenire XSS
 */
function escapeHtml(text: string): string {
  return text.replace(/[&<>'"]/g, (char) => {
    switch (char) {
      case '&': return '&amp;';
      case '<': return '&lt;';
      case '>': return '&gt;';
      case '"': return '&quot;';
      case "'": return '&#039;';
      default: return char;
    }
  });
}

/**
 * Estrae le mentions (@user) da un testo
 */
export function extractMentions(text: string): string[] {
  if (!text) return [];
  
  const matches = text.match(/@(\w+)/g);
  if (!matches) return [];
  
  return matches.map((match) => match.slice(1)); // Rimuove @
}

/**
 * Rileva la posizione del simbolo @ più vicino al cursore
 */
export function detectMentionTrigger(
  text: string,
  cursorPosition: number
): { anchor: number; query: string } | null {
  if (cursorPosition === 0) {
    return null;
  }

  // Cerca l'ultimo @ prima del cursore
  const beforeCursor = text.substring(0, cursorPosition);
  const lastAtIndex = beforeCursor.lastIndexOf('@');

  if (lastAtIndex === -1) {
    return null;
  }

  // Estrai la query dopo @
  const afterAt = text.substring(lastAtIndex + 1, cursorPosition);

  // Verifica che non ci siano spazi (mention non completata)
  if (/\s/.test(afterAt)) {
    return null;
  }

  return {
    anchor: lastAtIndex,
    query: afterAt,
  };
}

/**
 * Inserisce una mention nel testo alla posizione corretta
 */
export function insertMention(
  text: string,
  anchorPosition: number,
  cursorPosition: number,
  mentionName: string
): { newText: string; newCursorPosition: number } {
  const before = text.substring(0, anchorPosition);
  const after = text.substring(cursorPosition);
  const mention = `@${mentionName}`;
  
  const newText = before + mention + ' ' + after;
  const newCursorPosition = before.length + mention.length + 1;

  return { newText, newCursorPosition };
}

/**
 * Genera un ID univoco per un'opzione di mention
 */
export function getMentionOptionId(index: number, suggestionId?: number): string {
  return `fp-mention-option-${suggestionId ?? index}`;
}

/**
 * Filtra suggestions in base alla query
 */
export function filterSuggestions(
  suggestions: MentionSuggestion[],
  query: string
): MentionSuggestion[] {
  if (!query) return suggestions;

  const lowerQuery = query.toLowerCase();
  
  return suggestions.filter((suggestion) => {
    const nameMatch = suggestion.name.toLowerCase().includes(lowerQuery);
    const descMatch = suggestion.description?.toLowerCase().includes(lowerQuery);
    const emailMatch = suggestion.email?.toLowerCase().includes(lowerQuery);
    
    return nameMatch || descMatch || emailMatch;
  });
}

/**
 * Formatta un template con sostituzione variabili
 */
export function formatTemplate(template: string, ...args: (string | number)[]): string {
  let result = template;
  args.forEach((arg, index) => {
    const argStr = String(arg);
    result = result.replace(`%${index + 1}$s`, argStr);
    result = result.replace('%s', argStr);
    result = result.replace(`%${index + 1}$d`, argStr);
    result = result.replace('%d', argStr);
  });
  return result;
}

/**
 * Valida il corpo di un commento
 */
export function validateCommentBody(body: string): {
  valid: boolean;
  error?: string;
} {
  if (!body || typeof body !== 'string') {
    return { valid: false, error: 'Comment body is required' };
  }

  const trimmed = body.trim();
  
  if (trimmed.length === 0) {
    return { valid: false, error: 'Comment cannot be empty' };
  }

  if (trimmed.length > 5000) {
    return { valid: false, error: 'Comment is too long (max 5000 characters)' };
  }

  return { valid: true };
}

/**
 * Estrae gli ID degli utenti menzionati
 */
export function extractMentionIds(
  text: string,
  suggestions: MentionSuggestion[]
): number[] {
  const mentions = extractMentions(text);
  if (mentions.length === 0) return [];

  const ids = new Set<number>();
  
  mentions.forEach((mention) => {
    const suggestion = suggestions.find(
      (s) => s.name.toLowerCase() === mention.toLowerCase()
    );
    if (suggestion) {
      ids.add(suggestion.id);
    }
  });

  return Array.from(ids);
}
