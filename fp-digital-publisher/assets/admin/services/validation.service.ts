/**
 * Validation Service
 * Form validation and business rules
 */

export type ValidationResult = {
  valid: boolean;
  errors: string[];
};

export function validatePlanTitle(title: string): ValidationResult {
  const errors: string[] = [];
  
  if (!title || title.trim() === '') {
    errors.push('Title is required');
  }
  
  if (title.length > 200) {
    errors.push('Title must be less than 200 characters');
  }
  
  return {
    valid: errors.length === 0,
    errors,
  };
}

export function validateCaption(caption: string): ValidationResult {
  const errors: string[] = [];
  
  if (caption.length > 2200) {
    errors.push('Caption must be less than 2200 characters');
  }
  
  return {
    valid: errors.length === 0,
    errors,
  };
}

export function validateScheduledTime(dateString: string): ValidationResult {
  const errors: string[] = [];
  
  const date = new Date(dateString);
  if (isNaN(date.getTime())) {
    errors.push('Invalid date format');
    return { valid: false, errors };
  }
  
  const now = new Date();
  if (date < now) {
    errors.push('Scheduled time must be in the future');
  }
  
  return {
    valid: errors.length === 0,
    errors,
  };
}

export function validateComposerForm(data: {
  title: string;
  caption: string;
  scheduledAt: string;
}): ValidationResult {
  const errors: string[] = [];
  
  const titleResult = validatePlanTitle(data.title);
  errors.push(...titleResult.errors);
  
  const captionResult = validateCaption(data.caption);
  errors.push(...captionResult.errors);
  
  const dateResult = validateScheduledTime(data.scheduledAt);
  errors.push(...dateResult.errors);
  
  return {
    valid: errors.length === 0,
    errors,
  };
}