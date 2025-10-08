/**
 * Copy constants
 * All user-facing text strings with i18n support
 */

import { __, sprintf } from '@wordpress/i18n';
import { TEXT_DOMAIN } from './config';

export const copy = {
  common: {
    close: __('Close', TEXT_DOMAIN),
  },
  composer: {
    header: __('Content composer', TEXT_DOMAIN),
    subtitle: __('Complete the key information before scheduling.', TEXT_DOMAIN),
    stepperLabel: __('Composer progress', TEXT_DOMAIN),
    steps: {
      content: __('Content', TEXT_DOMAIN),
      variants: __('Variants', TEXT_DOMAIN),
      media: __('Media', TEXT_DOMAIN),
      schedule: __('Schedule', TEXT_DOMAIN),
      review: __('Review', TEXT_DOMAIN),
    },
    fields: {
      title: {
        label: __('Content title', TEXT_DOMAIN),
        placeholder: __('E.g. New product launch', TEXT_DOMAIN),
      },
      caption: {
        label: __('Caption', TEXT_DOMAIN),
        placeholder: __('Tell the story of the content and add call-to-actions.', TEXT_DOMAIN),
        hint: __('Tip: include CTAs, mentions, and short links.', TEXT_DOMAIN),
      },
      schedule: {
        label: __('Schedule', TEXT_DOMAIN),
      },
    },
    hashtagToggle: {
      label: __('Hashtags in the first comment (IG)', TEXT_DOMAIN),
      description: __('Automatically move hashtags to the first comment to keep the caption clean.', TEXT_DOMAIN),
      previewTitle: __('Comment preview', TEXT_DOMAIN),
      previewBody: __(' #marketing #launchday #fpDigitalPublisher', TEXT_DOMAIN).trimStart(),
    },
    actions: {
      saveDraft: __('Save draft', TEXT_DOMAIN),
      submit: __('Schedule content', TEXT_DOMAIN),
    },
    feedback: {
      blocking: __('Resolve the blocking items before scheduling.', TEXT_DOMAIN),
      scheduled: __('Content scheduled for %s.', TEXT_DOMAIN),
      fallbackDate: __('date to be defined', TEXT_DOMAIN),
      issuesPrefix: __('Fix: %s', TEXT_DOMAIN),
      noIssues: __('No blocking issues.', TEXT_DOMAIN),
      draftSaved: __('Draft saved in work-in-progress content.', TEXT_DOMAIN),
    },
    validation: {
      titleShort: __('Add a descriptive title (at least 5 characters).', TEXT_DOMAIN),
      captionShort: __('Fill the caption with at least 15 characters.', TEXT_DOMAIN),
      captionDetail: __('Add more details or CTAs in the caption.', TEXT_DOMAIN),
      scheduleInvalid: __('Set a future publication date.', TEXT_DOMAIN),
      hashtagsOff: __('Enable hashtags in the first comment to optimize IG reach.', TEXT_DOMAIN),
    },
  },
  preflight: {
    chipLabel: __('Preflight', TEXT_DOMAIN),
    modalTitle: __('Preflight details', TEXT_DOMAIN),
  },
  shortlinks: {
    empty: __('No short link configured. Create the first one to start tracking campaigns.', TEXT_DOMAIN),
    feedback: {
      loading: __('Loading short links…', TEXT_DOMAIN),
      empty: __('No short link configured. Create the first one to track campaigns.', TEXT_DOMAIN),
      open: __('Opening %s in a new tab.', TEXT_DOMAIN),
      copySuccess: __('URL copied to the clipboard.', TEXT_DOMAIN),
      copyError: __('Unable to copy to the clipboard.', TEXT_DOMAIN),
      disabling: __('Disabling in progress…', TEXT_DOMAIN),
      disabledEmpty: __('Short link disabled. There are no other active links.', TEXT_DOMAIN),
      disabled: __('Short link disabled successfully.', TEXT_DOMAIN),
      updated: __('Short link updated successfully.', TEXT_DOMAIN),
      created: __('Short link created successfully.', TEXT_DOMAIN),
    },
    section: {
      title: __('Short link', TEXT_DOMAIN),
      subtitle: __('Manage redirects and quick campaigns', TEXT_DOMAIN),
      createButton: __('New short link', TEXT_DOMAIN),
    },
    validation: {
      slugMissing: __('Enter a slug.', TEXT_DOMAIN),
      slugFormat: __('The slug can contain only letters, numbers, and hyphens.', TEXT_DOMAIN),
      targetMissing: __('Enter a destination URL.', TEXT_DOMAIN),
      targetInvalid: __('Enter a valid URL (e.g. https://example.com).', TEXT_DOMAIN),
    },
    preview: {
      shortlinkLabel: __('Short link:', TEXT_DOMAIN),
      utmLabel: __('UTM destination:', TEXT_DOMAIN),
      waiting: __('Waiting for a valid URL to compute the UTMs.', TEXT_DOMAIN),
    },
    errors: {
      disable: __('Error while disabling (%s).', TEXT_DOMAIN),
      save: __('Error while saving (%s).', TEXT_DOMAIN),
    },
    table: {
      slug: __('Slug', TEXT_DOMAIN),
      target: __('Destination', TEXT_DOMAIN),
      clicks: __('Clicks', TEXT_DOMAIN),
      lastClick: __('Last click', TEXT_DOMAIN),
      actions: __('Actions', TEXT_DOMAIN),
    },
    actions: {
      open: __('Open', TEXT_DOMAIN),
      copy: __('Copy URL', TEXT_DOMAIN),
      edit: __('Edit', TEXT_DOMAIN),
      disable: __('Disable', TEXT_DOMAIN),
    },
    menuLabel: __('Actions for %s', TEXT_DOMAIN),
    modal: {
      createTitle: __('New short link', TEXT_DOMAIN),
      editTitle: __('Edit short link', TEXT_DOMAIN),
      slugLabel: __('Slug', TEXT_DOMAIN),
      slugPlaceholder: __('promo-social', TEXT_DOMAIN),
      targetLabel: __('Destination URL', TEXT_DOMAIN),
      targetPlaceholder: __('https://example.com/promo', TEXT_DOMAIN),
      previewDefault: __('Fill the destination to generate the UTM preview.', TEXT_DOMAIN),
      cancel: __('Cancel', TEXT_DOMAIN),
      create: __('Create short link', TEXT_DOMAIN),
      update: __('Update link', TEXT_DOMAIN),
    },
  },
  trello: {
    modalTitle: __('Import content from Trello', TEXT_DOMAIN),
    listLabel: __('Trello list ID or URL', TEXT_DOMAIN),
    listPlaceholder: __('https://trello.com/b/.../list', TEXT_DOMAIN),
    apiKeyLabel: __('Trello API Key', TEXT_DOMAIN),
    tokenLabel: __('Trello Token', TEXT_DOMAIN),
    oauthLabel: __('OAuth Bearer token (optional)', TEXT_DOMAIN),
    oauthHint: __('Fill only if you use OAuth 2.0; leave empty to use API key + token.', TEXT_DOMAIN),
    fetch: __('Load cards', TEXT_DOMAIN),
    import: __('Import selection', TEXT_DOMAIN),
    loading: __('Loading Trello cards…', TEXT_DOMAIN),
    empty: __('No cards available in the selected list.', TEXT_DOMAIN),
    selectionHint: __('Select one or more cards to import as drafts.', TEXT_DOMAIN),
    missingCredentials: __('Enter API key + token or an OAuth token.', TEXT_DOMAIN),
    missingList: __('Enter a valid list ID or URL.', TEXT_DOMAIN),
    noSelection: __('Select at least one Trello card to import.', TEXT_DOMAIN),
    success: __('%d cards imported as drafts.', TEXT_DOMAIN),
    errorLoading: __('Unable to fetch Trello cards: %s', TEXT_DOMAIN),
    errorImport: __('Unable to import the selection: %s', TEXT_DOMAIN),
    context: __('Content will be imported as drafts for %1$s · %2$s.', TEXT_DOMAIN),
    attachmentsLabel: __('%d attachments', TEXT_DOMAIN),
    viewCard: __('Open in Trello', TEXT_DOMAIN),
  },
} as const;

// Message templates
export const messages = {
  SELECT_PLAN_MESSAGE: __('Select a plan from the calendar or kanban to inspect details.', TEXT_DOMAIN),
  STATUS_SUMMARY_TEMPLATE: __('%1$s — Status: %2$s', TEXT_DOMAIN),
  NEXT_SLOT_TEMPLATE: __('Next slot %s', TEXT_DOMAIN),
  ADVANCE_STATUS_TEMPLATE: __('Advance to %s', TEXT_DOMAIN),
  PLAN_ADVANCED_TEMPLATE: __('Plan advanced to %s.', TEXT_DOMAIN),
  PLAN_CONTEXT_TEMPLATE: __('Plan #%1$d — %2$s', TEXT_DOMAIN),
  APPROVALS_SELECT_MESSAGE: __('Select a plan to review the approvals workflow.', TEXT_DOMAIN),
  COMMENTS_SELECT_MESSAGE: __('Select a plan to read the latest comments.', TEXT_DOMAIN),
  APPROVAL_ADVANCE_ERROR_TEMPLATE: __('Unable to advance the plan (%s).', TEXT_DOMAIN),
  STATUS_CHANGE_TEMPLATE: __('Status changed from %1$s to %2$s.', TEXT_DOMAIN),
  STATUS_SET_TEMPLATE: __('Status set to %s.', TEXT_DOMAIN),
  APPROVALS_UPDATED_TEMPLATE: __('Approvals workflow updated for plan #%d.', TEXT_DOMAIN),
  COMMENTS_UPDATED_TEMPLATE: __('Comments updated for plan #%d.', TEXT_DOMAIN),
  COMMENTS_EMPTY_TEMPLATE: __('No comments available for plan #%d.', TEXT_DOMAIN),
  COMMENT_SENT_TEMPLATE: __('Comment sent for plan #%d.', TEXT_DOMAIN),
  NO_ACTIONS_MESSAGE: __('No further approval actions available for the selected plan.', TEXT_DOMAIN),
  FALLBACK_BRAND_LABEL: __('All brands', TEXT_DOMAIN),
  UNTITLED_PLAN: __('Untitled plan', TEXT_DOMAIN),
  PLAN_ID_TEMPLATE: __('Plan #%d', TEXT_DOMAIN),
} as const;

// Label mappings
export const APPROVAL_STATUS_LABELS: Record<string, string> = {
  draft: __('Draft', TEXT_DOMAIN),
  ready: __('Ready for review', TEXT_DOMAIN),
  approved: __('Approved', TEXT_DOMAIN),
  scheduled: __('Scheduled', TEXT_DOMAIN),
  published: __('Published', TEXT_DOMAIN),
  failed: __('Needs revision', TEXT_DOMAIN),
  changes_requested: __('Changes requested', TEXT_DOMAIN),
};

export const ALERT_SEVERITY_LABELS: Record<string, string> = {
  info: __('Informational', TEXT_DOMAIN),
  warning: __('Warning', TEXT_DOMAIN),
  critical: __('Critical', TEXT_DOMAIN),
};

export const LOG_STATUS_LABELS: Record<string, string> = {
  ok: __('Operational', TEXT_DOMAIN),
  warning: __('Warning', TEXT_DOMAIN),
  error: __('Error', TEXT_DOMAIN),
};

export type AlertTabKey = 'empty-week' | 'token-expiry' | 'failed-jobs';

export const ALERT_TAB_CONFIG: Record<AlertTabKey, { label: string; endpoint: string; empty: string }> = {
  'empty-week': {
    label: __('Empty week', TEXT_DOMAIN),
    endpoint: 'alerts/empty-week',
    empty: __('No gap detected for the current week.', TEXT_DOMAIN),
  },
  'token-expiry': {
    label: __('Expiring tokens', TEXT_DOMAIN),
    endpoint: 'alerts/token-expiry',
    empty: __('All tokens are up to date.', TEXT_DOMAIN),
  },
  'failed-jobs': {
    label: __('Failed jobs', TEXT_DOMAIN),
    endpoint: 'alerts/failed-jobs',
    empty: __('No failed jobs in the last 24 hours.', TEXT_DOMAIN),
  },
};

export default copy;