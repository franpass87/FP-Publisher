import { __, sprintf } from '@wordpress/i18n';

const TEXT_DOMAIN = 'fp-publisher';

interface BootConfig {
  restBase: string;
  nonce: string;
  version: string;
  brand?: string;
}

type AdminWindow = Window & {
  fpPublisherAdmin?: BootConfig;
};

type Suggestion = {
  datetime: string;
  score: number;
  reason: string;
};

type CommentItem = {
  id: number;
  body: string;
  created_at: string;
  author: {
    display_name: string;
  };
};

type ApprovalEvent = {
  id: number;
  status: 'submitted' | 'in_review' | 'approved' | 'changes_requested';
  note?: string | null;
  actor: {
    display_name: string;
  };
  occurred_at: string;
};

type MentionSuggestion = {
  id: number;
  name: string;
  slug?: string;
  description?: string | null;
};

type WPUser = {
  id: number;
  name: string;
  slug?: string;
  description?: string | null;
};

type ShortLink = {
  slug: string;
  target_url: string;
  clicks: number;
  last_click_at?: string | null;
};

type AlertSeverity = 'info' | 'warning' | 'critical';

type AlertRecord = {
  id: string;
  title: string;
  detail: string;
  severity: AlertSeverity;
  occurred_at?: string | null;
  meta?: string | null;
  action_label?: string | null;
  action_href?: string | null;
  action_type?: 'calendar' | 'job' | 'token';
  action_target?: string | null;
};

type AlertsResponse = {
  items?: AlertRecord[];
};

type LogStatus = 'ok' | 'warning' | 'error';

type LogEntry = {
  id: string;
  message: string;
  channel: string;
  status: LogStatus;
  payload?: string | null;
  stack?: string | null;
  created_at: string;
};

type LogsResponse = {
  items?: LogEntry[];
};

type ComposerState = {
  title: string;
  caption: string;
  scheduledAt: string;
  hashtagsFirst: boolean;
  issues: string[];
  notes: string[];
  score: number;
};

type PreflightInsight = {
  id: string;
  label: string;
  description: string;
  impact: number;
};

type CalendarSlotPayload = {
  channel?: string;
  scheduled_at?: string;
};

type CalendarPlanPayload = {
  id?: number;
  title?: string;
  status?: string;
  template?: { name?: string } | null;
  slots?: CalendarSlotPayload[];
};

type CalendarResponse = {
  items?: CalendarPlanPayload[];
};

type CalendarCellItem = {
  id: string;
  title: string;
  status: string;
  channel: string;
  isoDate: string;
  timeLabel: string;
  timestamp: number;
};

type TrelloAttachmentSummary = {
  id: string;
  name: string;
  url: string;
  mime_type: string;
};

type TrelloCardSummary = {
  id: string;
  name: string;
  description?: string;
  due?: string | null;
  url?: string;
  attachments: TrelloAttachmentSummary[];
};

type TrelloCredentials = {
  apiKey: string;
  token: string;
  oauthToken: string;
  listId: string;
  brand: string;
  channel: string;
};

const copy = {
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
};

const GRIP_ICON =
  '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M5 4.25a1.25 1.25 0 1 1 0 2.5 1.25 1.25 0 0 1 0-2.5zm5 0a1.25 1.25 0 1 1 0 2.5 1.25 1.25 0 0 1 0-2.5zm5 0a1.25 1.25 0 1 1 0 2.5 1.25 1.25 0 0 1 0-2.5z"/><path d="M5 8.75A1.25 1.25 0 1 1 5 11a1.25 1.25 0 0 1 0-2.5zm5 0A1.25 1.25 0 1 1 10 11a1.25 1.25 0 0 1 0-2.5zm5 0A1.25 1.25 0 1 1 15 11a1.25 1.25 0 0 1 0-2.5z"/><path d="M5 13.25a1.25 1.25 0 1 1 0 2.5 1.25 1.25 0 0 1 0-2.5zm5 0a1.25 1.25 0 1 1 0 2.5 1.25 1.25 0 0 1 0-2.5zm5 0a1.25 1.25 0 1 1 0 2.5 1.25 1.25 0 0 1 0-2.5z"/></svg>';

const PREFLIGHT_INSIGHTS: PreflightInsight[] = [
  {
    id: 'title',
    label: __('Title', TEXT_DOMAIN),
    description: __('Use a descriptive title to help the team understand the focus of the content.', TEXT_DOMAIN),
    impact: 30,
  },
  {
    id: 'caption',
    label: __('Caption', TEXT_DOMAIN),
    description: __('Complete the caption with call-to-actions and brand references.', TEXT_DOMAIN),
    impact: 30,
  },
  {
    id: 'schedule',
    label: __('Scheduling', TEXT_DOMAIN),
    description: __('Set a future date and time to avoid conflicts with other content.', TEXT_DOMAIN),
    impact: 25,
  },
  {
    id: 'hashtags',
    label: __('Hashtags', TEXT_DOMAIN),
    description: __('Confirm the hashtags in the first comment to increase Instagram reach.', TEXT_DOMAIN),
    impact: 15,
  },
];

const composerState: ComposerState = {
  title: '',
  caption: '',
  scheduledAt: '',
  hashtagsFirst: false,
  issues: [],
  notes: [],
  score: 100,
};

const composerInsightStatus = new Map<string, boolean>();
let preflightModalReturnFocus: HTMLElement | null = null;
let shortLinks: ShortLink[] = [];
let shortLinkModalReturnFocus: HTMLElement | null = null;
let shortLinkEditingSlug: string | null = null;
let activeShortLinkMenu: HTMLButtonElement | null = null;
let shortLinkModalKeydownHandler: ((event: KeyboardEvent) => void) | null = null;

const adminWindow = window as AdminWindow;
const config: BootConfig = adminWindow.fpPublisherAdmin ?? {
  restBase: '',
  nonce: '',
  version: '0.0.0',
  brand: 'brand-demo',
};

const mount = document.getElementById('fp-publisher-admin-app');

const now = new Date();
const monthKey = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
const activeChannel = 'instagram';
let calendarDensity: 'comfort' | 'compact' = 'comfort';

type AlertTabKey = 'empty-week' | 'token-expiry' | 'failed-jobs';

const ALERT_TAB_CONFIG: Record<AlertTabKey, { label: string; endpoint: string; empty: string }> = {
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

const ALERT_BRANDS = ['brand-demo', 'brand-nord', 'brand-sud'];
const ALERT_CHANNELS = ['instagram', 'facebook', 'linkedin', 'tiktok'];
const ALERT_SEVERITY_LABELS: Record<AlertSeverity, string> = {
  info: __('Informational', TEXT_DOMAIN),
  warning: __('Warning', TEXT_DOMAIN),
  critical: __('Critical', TEXT_DOMAIN),
};

let activeAlertTab: AlertTabKey = 'empty-week';
let alertBrandFilter = config.brand ?? 'brand-demo';
let alertChannelFilter: string = activeChannel;

const LOG_STATUS_LABELS: Record<LogStatus, string> = {
  ok: __('Operational', TEXT_DOMAIN),
  warning: __('Warning', TEXT_DOMAIN),
  error: __('Error', TEXT_DOMAIN),
};

const LOG_STATUS_TONES: Record<LogStatus, 'positive' | 'warning' | 'danger'> = {
  ok: 'positive',
  warning: 'warning',
  error: 'danger',
};

const LOG_CHANNEL_OPTIONS = ['all', 'instagram', 'facebook', 'linkedin', 'tiktok'];
const LOG_STATUS_OPTIONS: (LogStatus | 'all')[] = ['all', 'ok', 'warning', 'error'];

let logsChannelFilter: string = 'all';
let logsStatusFilter: LogStatus | 'all' = 'all';
let logsSearchTerm = '';
let logsSearchTimeout: number | undefined;

const logCopyCache = new Map<string, { payload?: string | null; stack?: string | null }>();

const adminBaseUrl = `${window.location.origin.replace(/\/$/, '')}/wp-admin/`;

const APPROVAL_STATUS_LABELS: Record<ApprovalEvent['status'], string> = {
  submitted: __('Submitted for review', TEXT_DOMAIN),
  in_review: __('In review', TEXT_DOMAIN),
  approved: __('Approved', TEXT_DOMAIN),
  changes_requested: __('Changes requested', TEXT_DOMAIN),
};

const APPROVAL_STATUS_TONES: Record<ApprovalEvent['status'], 'positive' | 'neutral' | 'warning'> = {
  submitted: 'neutral',
  in_review: 'neutral',
  approved: 'positive',
  changes_requested: 'warning',
};

type MentionState = {
  anchor: number;
  query: string;
  suggestions: MentionSuggestion[];
  activeIndex: number;
  list: HTMLUListElement | null;
  textarea: HTMLTextAreaElement | null;
};

const mentionState: MentionState = {
  anchor: -1,
  query: '',
  suggestions: [],
  activeIndex: -1,
  list: null,
  textarea: null,
};

let mentionFetchTimeout: number | undefined;
let mentionRequestId = 0;

function formatDate(date: Date): string {
  return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}

function formatTime(date: Date): string {
  return date.toLocaleTimeString([], {
    hour: '2-digit',
    minute: '2-digit',
  });
}

function formatHumanDate(date: Date): string {
  return date.toLocaleDateString([], {
    weekday: 'short',
    day: 'numeric',
    month: 'short',
  });
}

function escapeHtml(value: string): string {
  return value.replace(/[&<>'"]/g, (char) => {
    switch (char) {
      case '&':
        return '&amp;';
      case '<':
        return '&lt;';
      case '>':
        return '&gt;';
      case '"':
        return '&quot;';
      case "'":
        return '&#039;';
      default:
        return char;
    }
  });
}

function toDomId(prefix: string, value: string): string {
  const sanitized = value.replace(/[^a-zA-Z0-9_-]/g, '-').toLowerCase();
  return `${prefix}-${sanitized}`;
}

function truncateText(value: string, max = 72): string {
  if (value.length <= max) {
    return value;
  }

  return `${value.slice(0, max - 1)}…`;
}

function formatLastClickAt(iso?: string | null): string {
  if (!iso) {
    return '—';
  }

  const parsed = new Date(iso);
  if (Number.isNaN(parsed.getTime())) {
    return '—';
  }

  return parsed.toLocaleString(undefined, {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}

function buildShortLinkUrl(slug: string): string {
  const origin = window.location.origin.replace(/\/$/, '');
  return `${origin}/go/${slug}`;
}

function initialsForName(name: string): string {
  const segments = name
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2);
  if (!segments.length) {
    return '??';
  }

  return segments
    .map((segment) => segment.charAt(0).toUpperCase())
    .join('');
}

function formatCommentBody(body: string): string {
  const escaped = escapeHtml(body);
  return escaped
    .replace(/(@[\w._-]+)/g, '<span class="fp-comments__mention-token">$1</span>')
    .replace(/\n/g, '<br />');
}

function announceCommentUpdate(message: string): void {
  const region = document.getElementById('fp-comments-announcer');
  if (region) {
    region.textContent = message;
  }
}

function announceApprovalsUpdate(message: string): void {
  const region = document.getElementById('fp-approvals-announcer');
  if (region) {
    region.textContent = message;
  }
}

function announceAlertsUpdate(message: string): void {
  const region = document.getElementById('fp-alerts-announcer');
  if (region) {
    region.textContent = message;
  }
}

function announceLogsUpdate(message: string): void {
  const region = document.getElementById('fp-logs-announcer');
  if (region) {
    region.textContent = message;
  }
}

function resolvePlanTitle(plan: CalendarPlanPayload): string {
  const rawTitle = (plan.title ?? plan.template?.name ?? '').trim();
  if (rawTitle) {
    return rawTitle;
  }

  if (plan.id) {
    return sprintf(__('Plan #%d', TEXT_DOMAIN), plan.id);
  }

  return __('Untitled plan', TEXT_DOMAIN);
}

function humanizeLabel(value: string): string {
  if (!value) {
    return value;
  }

  return value
    .split(/[-_\s]+/)
    .filter(Boolean)
    .map((segment) => segment.charAt(0).toUpperCase() + segment.slice(1))
    .join(' ');
}

function buildSelectOptions(values: string[], current: string): string {
  return values
    .filter((value) => value && typeof value === 'string')
    .map((value) => {
      const normalized = value.trim();
      const selected = normalized === current ? ' selected' : '';
      return `<option value="${escapeHtml(normalized)}"${selected}>${escapeHtml(humanizeLabel(normalized))}</option>`;
    })
    .join('');
}

function updateAlertTabs(activeKey: AlertTabKey): void {
  const buttons = document.querySelectorAll<HTMLButtonElement>('[data-alert-tab]');
  buttons.forEach((button) => {
    const key = (button.dataset.alertTab as AlertTabKey | undefined) ?? 'empty-week';
    const isActive = key === activeKey;
    button.classList.toggle('is-active', isActive);
    button.setAttribute('aria-selected', isActive ? 'true' : 'false');
    button.setAttribute('tabindex', isActive ? '0' : '-1');
  });
}

function renderAlertAction(item: AlertRecord): string {
  if (item.action_href) {
    const href = escapeHtml(item.action_href);
    const label = escapeHtml(item.action_label ?? __('Open details', TEXT_DOMAIN));
    return `<a class="button fp-alerts__action" href="${href}" target="_blank" rel="noopener noreferrer">${label}</a>`;
  }

  if (item.action_type) {
    const label = escapeHtml(item.action_label ?? __('Open details', TEXT_DOMAIN));
    const targetAttr = item.action_target ? ` data-alert-target="${escapeHtml(item.action_target)}"` : '';
    return `<button type="button" class="button fp-alerts__action" data-alert-action="${item.action_type}"${targetAttr}>${label}</button>`;
  }

  return '';
}

function alertSeverityTone(severity: AlertSeverity): 'neutral' | 'warning' | 'danger' {
  if (severity === 'critical') {
    return 'danger';
  }
  if (severity === 'warning') {
    return 'warning';
  }
  return 'neutral';
}

function renderAlertItems(items: AlertRecord[]): string {
  const listItems = items
    .map((item) => {
      const severity = item.severity ?? 'info';
      const tone = alertSeverityTone(severity);
      const severityLabel = ALERT_SEVERITY_LABELS[severity] ?? ALERT_SEVERITY_LABELS.info;
      const timestamp = item.occurred_at
        ? `<time datetime="${escapeHtml(item.occurred_at)}">${new Date(item.occurred_at).toLocaleString()}</time>`
        : '';
      const metaParts = [] as string[];
      if (item.meta) {
        metaParts.push(escapeHtml(item.meta));
      }
      const metaMarkup = metaParts.length
        ? `<p class="fp-alerts__meta">${metaParts.join(' · ')}</p>`
        : '';
      const detailMarkup = item.detail ? `<p class="fp-alerts__detail">${escapeHtml(item.detail)}</p>` : '';
      const actionMarkup = renderAlertAction(item);
      const actionsWrapper = actionMarkup ? `<div class="fp-alerts__actions">${actionMarkup}</div>` : '';

      return `
        <li class="fp-alerts__item" role="listitem" data-severity="${escapeHtml(severity)}">
          <header class="fp-alerts__item-header">
            <span class="fp-status-badge" data-tone="${tone}">${escapeHtml(severityLabel)}</span>
            <div class="fp-alerts__item-heading">
              <strong>${escapeHtml(item.title)}</strong>
              ${timestamp}
            </div>
          </header>
          ${detailMarkup}
          ${metaMarkup}
          ${actionsWrapper}
        </li>
      `;
    })
    .join('');

  return `<ul class="fp-alerts__list" role="list">${listItems}</ul>`;
}

async function loadAlertsData(tabKey: AlertTabKey): Promise<void> {
  const panel = document.getElementById('fp-alerts-panel');
  if (!panel) {
    return;
  }

  activeAlertTab = tabKey;
  updateAlertTabs(tabKey);

  panel.innerHTML = `<p class="fp-alerts__loading">${escapeHtml(__('Loading alerts…', TEXT_DOMAIN))}</p>`;

  const tabConfig = ALERT_TAB_CONFIG[tabKey];
  const params = new URLSearchParams();
  if (alertBrandFilter) {
    params.set('brand', alertBrandFilter);
  }
  if (alertChannelFilter) {
    params.set('channel', alertChannelFilter);
  }

  try {
    const query = params.toString();
    const url = `${config.restBase}/${tabConfig.endpoint}${query ? `?${query}` : ''}`;
    const response = await fetchJSON<AlertsResponse>(url);
    const items = Array.isArray(response.items) ? response.items : [];

    if (!items.length) {
      panel.innerHTML = `<p class="fp-alerts__empty">${escapeHtml(tabConfig.empty)}</p>`;
      announceAlertsUpdate(tabConfig.empty);
      return;
    }

    panel.innerHTML = renderAlertItems(items);
    announceAlertsUpdate(
      sprintf(__('Updated %1$d alerts for the %2$s view.', TEXT_DOMAIN), items.length, tabConfig.label)
    );
  } catch (error) {
    panel.innerHTML = `<p class="fp-alerts__error">${escapeHtml(
      sprintf(__('Unable to load alerts (%s).', TEXT_DOMAIN), (error as Error).message)
    )}</p>`;
    announceAlertsUpdate(__('Error while fetching alerts.', TEXT_DOMAIN));
  }
}

function renderAlertsWidget(container: HTMLElement): void {
  const brandOptions = Array.from(new Set([alertBrandFilter, config.brand ?? '', ...ALERT_BRANDS])).filter(Boolean);
  const channelOptions = Array.from(new Set([alertChannelFilter, activeChannel, ...ALERT_CHANNELS])).filter(Boolean);
  const tabKeys = Object.keys(ALERT_TAB_CONFIG) as AlertTabKey[];

  container.innerHTML = `
    <section class="fp-alerts" aria-labelledby="fp-alerts-title">
      <header class="fp-alerts__header">
        <div>
          <h2 id="fp-alerts-title">${escapeHtml(__('Operational alerts', TEXT_DOMAIN))}</h2>
          <p class="fp-alerts__hint">${escapeHtml(__('Weekly priorities for the marketing team.', TEXT_DOMAIN))}</p>
        </div>
        <div class="fp-alerts__filters">
          <label class="fp-alerts__filter">
            <span>${escapeHtml(__('Brand', TEXT_DOMAIN))}</span>
            <select id="fp-alerts-brand">${buildSelectOptions(brandOptions, alertBrandFilter)}</select>
          </label>
          <label class="fp-alerts__filter">
            <span>${escapeHtml(__('Channel', TEXT_DOMAIN))}</span>
            <select id="fp-alerts-channel">${buildSelectOptions(channelOptions, alertChannelFilter)}</select>
          </label>
        </div>
      </header>
      <nav class="fp-alerts__tabs" role="tablist" aria-label="${escapeHtml(__('Alert categories', TEXT_DOMAIN))}">
        ${tabKeys
          .map((key) => {
            const tab = ALERT_TAB_CONFIG[key];
            const isActive = key === activeAlertTab;
            return `<button type="button" class="fp-alerts__tab${isActive ? ' is-active' : ''}" role="tab" data-alert-tab="${key}" aria-controls="fp-alerts-panel" aria-selected="${isActive ? 'true' : 'false'}">${tab.label}</button>`;
          })
          .join('')}
      </nav>
      <div id="fp-alerts-panel" class="fp-alerts__panel" role="tabpanel" tabindex="0" aria-live="polite"></div>
      <div id="fp-alerts-announcer" class="screen-reader-text" aria-live="polite"></div>
    </section>
  `;

  const brandSelect = container.querySelector<HTMLSelectElement>('#fp-alerts-brand');
  brandSelect?.addEventListener('change', () => {
    alertBrandFilter = brandSelect.value;
    void loadAlertsData(activeAlertTab);
  });

  const channelSelect = container.querySelector<HTMLSelectElement>('#fp-alerts-channel');
  channelSelect?.addEventListener('change', () => {
    alertChannelFilter = channelSelect.value;
    void loadAlertsData(activeAlertTab);
  });

  const tabButtons = container.querySelectorAll<HTMLButtonElement>('[data-alert-tab]');
  tabButtons.forEach((button) => {
    button.addEventListener('click', (event) => {
      event.preventDefault();
      const key = (button.dataset.alertTab as AlertTabKey | undefined) ?? 'empty-week';
      void loadAlertsData(key);
    });
  });

  container.addEventListener('click', (event) => {
    const target = (event.target as HTMLElement).closest<HTMLButtonElement>('[data-alert-action]');
    if (!target) {
      return;
    }

    event.preventDefault();
    handleAlertAction(target);
  });

  updateAlertTabs(activeAlertTab);
  void loadAlertsData(activeAlertTab);
}

function resolveAdminUrl(path: string): string {
  if (/^https?:/i.test(path)) {
    return path;
  }

  const normalized = path.replace(/^\/+/, '');
  return `${adminBaseUrl}${normalized}`;
}

function handleAlertAction(button: HTMLButtonElement): void {
  const action = (button.dataset.alertAction as AlertRecord['action_type'] | undefined) ?? null;
  if (!action) {
    return;
  }

  if (action === 'calendar') {
    const calendar = document.getElementById('fp-calendar');
    calendar?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    announceAlertsUpdate(__('Focused the calendar to schedule the empty week.', TEXT_DOMAIN));
    return;
  }

  if (action === 'job') {
    const jobId = button.dataset.alertTarget ?? '';
    const url = jobId
      ? `${adminBaseUrl}admin.php?page=fp-jobs&job=${encodeURIComponent(jobId)}`
      : `${adminBaseUrl}admin.php?page=fp-jobs`;
    window.open(url, '_blank', 'noopener');
    announceAlertsUpdate(__('Job opened in a new tab.', TEXT_DOMAIN));
    return;
  }

  if (action === 'token') {
    const target = button.dataset.alertTarget ?? 'admin.php?page=fp-integrations';
    const url = resolveAdminUrl(target);
    window.open(url, '_blank', 'noopener');
    announceAlertsUpdate(__('Integrations page opened to renew the token.', TEXT_DOMAIN));
  }
}

function renderLogsEntries(entries: LogEntry[]): string {
  const listItems = entries
    .map((entry) => {
      const tone = LOG_STATUS_TONES[entry.status] ?? 'warning';
      const label = LOG_STATUS_LABELS[entry.status] ?? humanizeLabel(entry.status);
      const timestamp = new Date(entry.created_at).toLocaleString();
      const payloadDisabled = !entry.payload;
      const stackDisabled = !entry.stack;
      const payloadLabel = __('Payload', TEXT_DOMAIN);
      const stackLabel = __('Stack trace', TEXT_DOMAIN);
      const copyPayloadLabel = __('Copy payload', TEXT_DOMAIN);
      const copyStackLabel = __('Copy stack', TEXT_DOMAIN);

      return `
        <li class="fp-logs__entry" role="listitem" data-status="${escapeHtml(entry.status)}">
          <header class="fp-logs__entry-header">
            <span class="fp-status-badge" data-tone="${tone}">${escapeHtml(label)}</span>
            <div class="fp-logs__entry-meta">
              <span class="fp-logs__channel">${escapeHtml(entry.channel)}</span>
              <time datetime="${escapeHtml(entry.created_at)}">${timestamp}</time>
            </div>
          </header>
          <p class="fp-logs__message">${escapeHtml(entry.message)}</p>
          <div class="fp-logs__blocks">
            <section class="fp-logs__block">
              <header class="fp-logs__block-header">
                <h4>${escapeHtml(payloadLabel)}</h4>
                <button
                  type="button"
                  class="button fp-logs__copy"
                  data-log-copy="payload"
                  data-log-id="${escapeHtml(entry.id)}"
                  data-label="${escapeHtml(copyPayloadLabel)}"
                  aria-label="${escapeHtml(sprintf(__('Copy payload for log %s', TEXT_DOMAIN), entry.id))}"
                  ${payloadDisabled ? 'disabled' : ''}
                >${escapeHtml(copyPayloadLabel)}</button>
              </header>
              <pre class="fp-logs__code">${entry.payload ? escapeHtml(entry.payload) : '—'}</pre>
            </section>
            <section class="fp-logs__block">
              <header class="fp-logs__block-header">
                <h4>${escapeHtml(stackLabel)}</h4>
                <button
                  type="button"
                  class="button fp-logs__copy"
                  data-log-copy="stack"
                  data-log-id="${escapeHtml(entry.id)}"
                  data-label="${escapeHtml(copyStackLabel)}"
                  aria-label="${escapeHtml(sprintf(__('Copy stack trace for log %s', TEXT_DOMAIN), entry.id))}"
                  ${stackDisabled ? 'disabled' : ''}
                >${escapeHtml(copyStackLabel)}</button>
              </header>
              <pre class="fp-logs__code">${entry.stack ? escapeHtml(entry.stack) : '—'}</pre>
            </section>
          </div>
        </li>
      `;
    })
    .join('');

  return `<ul class="fp-logs__list-items" role="list">${listItems}</ul>`;
}

async function loadLogs(): Promise<void> {
  const list = document.getElementById('fp-logs-list');
  if (!list) {
    return;
  }

  list.innerHTML = `<p class="fp-logs__loading">${escapeHtml(__('Loading logs…', TEXT_DOMAIN))}</p>`;

  const params = new URLSearchParams();
  if (config.brand) {
    params.set('brand', config.brand);
  }
  if (logsChannelFilter !== 'all') {
    params.set('channel', logsChannelFilter);
  }
  if (logsStatusFilter !== 'all') {
    params.set('status', logsStatusFilter);
  }
  if (logsSearchTerm) {
    params.set('search', logsSearchTerm);
  }

  try {
    const query = params.toString();
    const endpoint = `${config.restBase}/logs${query ? `?${query}` : ''}`;
    const data = await fetchJSON<LogsResponse>(endpoint);
    const items = Array.isArray(data.items) ? data.items : [];

    if (!items.length) {
      list.innerHTML = `<p class="fp-logs__empty">${escapeHtml(__('No logs found for the selected filters.', TEXT_DOMAIN))}</p>`;
      announceLogsUpdate(__('No logs available for the selected filters.', TEXT_DOMAIN));
      logCopyCache.clear();
      return;
    }

    logCopyCache.clear();
    items.forEach((entry) => {
      logCopyCache.set(entry.id, { payload: entry.payload, stack: entry.stack });
    });

    list.innerHTML = renderLogsEntries(items);
    announceLogsUpdate(sprintf(__('%d logs loaded.', TEXT_DOMAIN), items.length));
  } catch (error) {
    list.innerHTML = `<p class="fp-logs__error">${escapeHtml(
      sprintf(__('Unable to load logs (%s).', TEXT_DOMAIN), (error as Error).message),
    )}</p>`;
    announceLogsUpdate(__('Error while fetching logs.', TEXT_DOMAIN));
  }
}

function renderLogsWidget(container: HTMLElement): void {
  const channelButtons = LOG_CHANNEL_OPTIONS.map((value) => {
    const isActive = value === logsChannelFilter;
    const label = value === 'all' ? __('All channels', TEXT_DOMAIN) : humanizeLabel(value);
    return `<button type="button" class="fp-logs__filter${isActive ? ' is-active' : ''}" data-log-channel="${value}" aria-pressed="${isActive ? 'true' : 'false'}">${label}</button>`;
  }).join('');

  const statusButtons = LOG_STATUS_OPTIONS.map((value) => {
    const isActive = value === logsStatusFilter;
    const label =
      value === 'all'
        ? __('All statuses', TEXT_DOMAIN)
        : LOG_STATUS_LABELS[value] ?? humanizeLabel(String(value));
    return `<button type="button" class="fp-logs__filter${isActive ? ' is-active' : ''}" data-log-status="${value}" aria-pressed="${isActive ? 'true' : 'false'}">${label}</button>`;
  }).join('');

  container.innerHTML = `
    <section class="fp-logs" aria-labelledby="fp-logs-title">
      <header class="fp-logs__header">
        <div>
          <h2 id="fp-logs-title">${escapeHtml(__('Operational logs', TEXT_DOMAIN))}</h2>
          <p class="fp-logs__hint">${escapeHtml(__('Monitoring jobs and diagnostics in real time.', TEXT_DOMAIN))}</p>
        </div>
        <form class="fp-logs__search" role="search">
          <label class="screen-reader-text" for="fp-logs-search">${escapeHtml(__('Search logs', TEXT_DOMAIN))}</label>
          <input
            type="search"
            id="fp-logs-search"
            placeholder="${escapeHtml(__('Search by message or ID', TEXT_DOMAIN))}"
            value="${escapeHtml(logsSearchTerm)}"
          />
        </form>
      </header>
      <div class="fp-logs__filters" data-log-filter="channel" role="group" aria-label="${escapeHtml(
        __('Filter by channel', TEXT_DOMAIN),
      )}">
        ${channelButtons}
      </div>
      <div class="fp-logs__filters" data-log-filter="status" role="group" aria-label="${escapeHtml(
        __('Filter by status', TEXT_DOMAIN),
      )}">
        ${statusButtons}
      </div>
      <div id="fp-logs-list" class="fp-logs__list" aria-live="polite"></div>
      <div id="fp-logs-announcer" class="screen-reader-text" aria-live="polite"></div>
    </section>
  `;

  const searchInput = container.querySelector<HTMLInputElement>('#fp-logs-search');
  searchInput?.addEventListener('input', () => {
    logsSearchTerm = searchInput.value.trim();
    if (logsSearchTimeout) {
      window.clearTimeout(logsSearchTimeout);
    }
    logsSearchTimeout = window.setTimeout(() => {
      void loadLogs();
    }, 240);
  });

  const channelGroup = container.querySelector('[data-log-filter="channel"]');
  channelGroup?.querySelectorAll<HTMLButtonElement>('button[data-log-channel]').forEach((button) => {
    button.addEventListener('click', (event) => {
      event.preventDefault();
      const value = button.dataset.logChannel ?? 'all';
      logsChannelFilter = value;
      channelGroup.querySelectorAll<HTMLButtonElement>('button[data-log-channel]').forEach((btn) => {
        const isActive = btn.dataset.logChannel === value;
        btn.classList.toggle('is-active', isActive);
        btn.setAttribute('aria-pressed', isActive ? 'true' : 'false');
      });
      void loadLogs();
    });
  });

  const statusGroup = container.querySelector('[data-log-filter="status"]');
  statusGroup?.querySelectorAll<HTMLButtonElement>('button[data-log-status]').forEach((button) => {
    button.addEventListener('click', (event) => {
      event.preventDefault();
      const value = (button.dataset.logStatus as LogStatus | 'all' | undefined) ?? 'all';
      logsStatusFilter = value;
      statusGroup.querySelectorAll<HTMLButtonElement>('button[data-log-status]').forEach((btn) => {
        const isActive = btn.dataset.logStatus === value;
        btn.classList.toggle('is-active', isActive);
        btn.setAttribute('aria-pressed', isActive ? 'true' : 'false');
      });
      void loadLogs();
    });
  });

  container.addEventListener('click', (event) => {
    const target = (event.target as HTMLElement).closest<HTMLButtonElement>('[data-log-copy]');
    if (!target) {
      return;
    }

    event.preventDefault();
    const field = target.dataset.logCopy === 'stack' ? 'stack' : 'payload';
    void copyLogField(target, field);
  });

  void loadLogs();
}

async function writeClipboardText(value: string): Promise<void> {
  if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
    await navigator.clipboard.writeText(value);
    return;
  }

  const textarea = document.createElement('textarea');
  textarea.value = value;
  textarea.setAttribute('readonly', 'true');
  textarea.style.position = 'fixed';
  textarea.style.opacity = '0';
  document.body.appendChild(textarea);
  textarea.select();
  document.execCommand('copy');
  document.body.removeChild(textarea);
}

async function copyLogField(button: HTMLButtonElement, field: 'payload' | 'stack'): Promise<void> {
  if (button.disabled) {
    return;
  }

  const logId = button.dataset.logId ?? '';
  if (!logId) {
    return;
  }

  const entry = logCopyCache.get(logId);
  if (!entry) {
    return;
  }

  const value = field === 'payload' ? entry.payload : entry.stack;
  if (!value) {
    return;
  }

  const originalLabel = button.dataset.label ?? button.textContent ?? '';

  try {
    await writeClipboardText(value);
    button.classList.add('is-copied');
    button.textContent = __('Copied', TEXT_DOMAIN);
    const label = field === 'payload' ? __('Payload', TEXT_DOMAIN) : __('Stack trace', TEXT_DOMAIN);
    announceLogsUpdate(sprintf(__('%s copied to the clipboard.', TEXT_DOMAIN), label));
  } catch (error) {
    console.error(__('Unable to copy log', TEXT_DOMAIN), error);
    button.classList.add('has-error');
    button.textContent = __('Copy error', TEXT_DOMAIN);
    announceLogsUpdate(__('Unable to copy to the clipboard.', TEXT_DOMAIN));
  } finally {
    window.setTimeout(() => {
      button.classList.remove('is-copied', 'has-error');
      button.textContent = originalLabel;
    }, 1800);
  }
}

function renderComposer(container: HTMLElement): void {
  container.innerHTML = `
    <header class="fp-composer__header">
      <div>
        <h2>${copy.composer.header}</h2>
        <p class="fp-composer__subtitle">${copy.composer.subtitle}</p>
      </div>
      <button
        type="button"
        class="fp-preflight-chip"
        id="fp-preflight-chip"
        aria-haspopup="dialog"
        aria-controls="fp-preflight-modal"
        aria-expanded="false"
      >
        <span class="fp-preflight-chip__label">${copy.preflight.chipLabel}</span>
        <span class="fp-preflight-chip__score" id="fp-preflight-chip-score" aria-live="polite">100</span>
      </button>
    </header>
    <nav class="fp-stepper" aria-label="${copy.composer.stepperLabel}">
      <ol class="fp-stepper__list">
        <li class="fp-stepper__item is-active" data-step="content">
          <span class="fp-stepper__bullet" aria-hidden="true">1</span>
          <span class="fp-stepper__label">${copy.composer.steps.content}</span>
        </li>
        <li class="fp-stepper__item" data-step="variants">
          <span class="fp-stepper__bullet" aria-hidden="true">2</span>
          <span class="fp-stepper__label">${copy.composer.steps.variants}</span>
        </li>
        <li class="fp-stepper__item" data-step="media">
          <span class="fp-stepper__bullet" aria-hidden="true">3</span>
          <span class="fp-stepper__label">${copy.composer.steps.media}</span>
        </li>
        <li class="fp-stepper__item" data-step="programma">
          <span class="fp-stepper__bullet" aria-hidden="true">4</span>
          <span class="fp-stepper__label">${copy.composer.steps.schedule}</span>
        </li>
        <li class="fp-stepper__item" data-step="review">
          <span class="fp-stepper__bullet" aria-hidden="true">5</span>
          <span class="fp-stepper__label">${copy.composer.steps.review}</span>
        </li>
      </ol>
    </nav>
    <form id="fp-composer-form" class="fp-composer__form" novalidate>
      <div class="fp-field">
        <label for="fp-composer-title">${copy.composer.fields.title.label}</label>
        <input type="text" id="fp-composer-title" name="title" placeholder="${copy.composer.fields.title.placeholder}" required />
      </div>
      <div class="fp-field">
        <label for="fp-composer-caption">${copy.composer.fields.caption.label}</label>
        <textarea
          id="fp-composer-caption"
          name="caption"
          rows="4"
          placeholder="${copy.composer.fields.caption.placeholder}"
          required
        ></textarea>
        <p class="fp-field__hint">${copy.composer.fields.caption.hint}</p>
      </div>
      <div class="fp-field fp-field--inline">
        <label for="fp-composer-schedule">${copy.composer.fields.schedule.label}</label>
        <input type="datetime-local" id="fp-composer-schedule" name="scheduled_at" required />
      </div>
      <div class="fp-composer__toggle">
        <label class="fp-switch" for="fp-hashtag-toggle">
          <input
            type="checkbox"
            id="fp-hashtag-toggle"
            aria-describedby="fp-hashtag-hint"
            aria-controls="fp-hashtag-preview"
            aria-expanded="false"
          />
          <span class="fp-switch__control" aria-hidden="true"></span>
          <span class="fp-switch__label">${copy.composer.hashtagToggle.label}</span>
        </label>
        <p id="fp-hashtag-hint" class="fp-composer__hint">
          ${copy.composer.hashtagToggle.description}
        </p>
      </div>
      <section id="fp-hashtag-preview" class="fp-composer__preview" hidden aria-live="polite">
        <h3>${copy.composer.hashtagToggle.previewTitle}</h3>
        <p>${copy.composer.hashtagToggle.previewBody}</p>
      </section>
      <div class="fp-composer__actions">
        <button type="button" class="button" id="fp-composer-save-draft">${copy.composer.actions.saveDraft}</button>
        <button type="submit" class="button button-primary" id="fp-composer-submit" data-tooltip-position="top">
          ${copy.composer.actions.submit}
        </button>
      </div>
      <p id="fp-composer-issues" class="fp-composer__issues" role="status" aria-live="polite"></p>
      <div id="fp-composer-feedback" class="fp-composer__feedback" aria-live="polite"></div>
    </form>
    <div class="fp-modal" id="fp-preflight-modal" role="dialog" aria-modal="true" aria-labelledby="fp-preflight-title" hidden>
      <div class="fp-modal__backdrop" data-modal-overlay></div>
      <div class="fp-modal__dialog" role="document">
        <header class="fp-modal__header">
          <h2 id="fp-preflight-title">${copy.preflight.modalTitle}</h2>
          <button type="button" class="fp-modal__close" data-modal-close aria-label="${escapeHtml(copy.common.close)}">×</button>
        </header>
        <p id="fp-preflight-score" class="fp-modal__score" aria-live="polite"></p>
        <ul id="fp-preflight-list" class="fp-modal__list"></ul>
      </div>
    </div>
  `;
}

function initComposer(): void {
  const composer = document.getElementById('fp-composer');
  if (!composer) {
    return;
  }

  const form = composer.querySelector<HTMLFormElement>('#fp-composer-form');
  const titleInput = form?.querySelector<HTMLInputElement>('#fp-composer-title') ?? null;
  const captionInput = form?.querySelector<HTMLTextAreaElement>('#fp-composer-caption') ?? null;
  const scheduleInput = form?.querySelector<HTMLInputElement>('#fp-composer-schedule') ?? null;
  const submitButton = form?.querySelector<HTMLButtonElement>('#fp-composer-submit') ?? null;
  const saveDraftButton = form?.querySelector<HTMLButtonElement>('#fp-composer-save-draft') ?? null;
  const issuesOutput = form?.querySelector<HTMLParagraphElement>('#fp-composer-issues') ?? null;
  const feedbackOutput = form?.querySelector<HTMLDivElement>('#fp-composer-feedback') ?? null;
  const hashtagToggle = form?.querySelector<HTMLInputElement>('#fp-hashtag-toggle') ?? null;
  const hashtagPreview = form?.querySelector<HTMLElement>('#fp-hashtag-preview') ?? null;
  const stepperItems = Array.from(composer.querySelectorAll<HTMLElement>('.fp-stepper__item'));
  const preflightChip = composer.querySelector<HTMLButtonElement>('#fp-preflight-chip');
  const preflightChipScore = composer.querySelector<HTMLElement>('#fp-preflight-chip-score');
  const modal = composer.querySelector<HTMLElement>('#fp-preflight-modal');
  const modalList = modal?.querySelector<HTMLUListElement>('#fp-preflight-list') ?? null;
  const modalScore = modal?.querySelector<HTMLElement>('#fp-preflight-score') ?? null;
  const modalClose = modal?.querySelector<HTMLButtonElement>('[data-modal-close]') ?? null;
  const modalOverlay = modal?.querySelector<HTMLElement>('[data-modal-overlay]') ?? null;

  if (
    !form ||
    !titleInput ||
    !captionInput ||
    !scheduleInput ||
    !submitButton ||
    !preflightChip ||
    !preflightChipScore ||
    !modal ||
    !modalList ||
    !modalScore ||
    !modalClose ||
    !modalOverlay ||
    !issuesOutput ||
    !feedbackOutput ||
    !hashtagToggle ||
    !hashtagPreview
  ) {
    return;
  }

  const getFocusable = (root: HTMLElement): HTMLElement[] => {
    return Array.from(
      root.querySelectorAll<HTMLElement>(
        'a[href], button:not([disabled]), textarea, input, select, [tabindex]:not([tabindex="-1"])',
      ),
    ).filter((node) => node.offsetParent !== null);
  };

  const updatePreflightModal = (): void => {
    modalScore.textContent = `Score complessivo: ${composerState.score}/100`;
    modalList.innerHTML = PREFLIGHT_INSIGHTS.map((insight) => {
      const resolved = composerInsightStatus.get(insight.id) === true;
      const status = resolved ? 'Completato' : 'Da rivedere';
      return `
        <li class="fp-modal__item" data-status="${resolved ? 'done' : 'pending'}">
          <div>
            <span class="fp-modal__item-label">${insight.label}</span>
            <span class="fp-modal__item-status">${status}</span>
          </div>
          <p>${insight.description}</p>
        </li>
      `;
    }).join('');
  };

  const updatePreflightChip = (): void => {
    const tone = composerState.score >= 80 ? 'positive' : composerState.score >= 60 ? 'warning' : 'danger';
    preflightChip.dataset.tone = tone;
    preflightChipScore.textContent = String(composerState.score);
    updatePreflightModal();
  };

  const updateStepper = (): void => {
    const hasTitle = composerInsightStatus.get('title') === true;
    const hasCaption = composerInsightStatus.get('caption') === true;
    const hasSchedule = composerInsightStatus.get('schedule') === true;
    const hashtagsReady = composerInsightStatus.get('hashtags') === true;
    const steps = ['content', 'variants', 'media', 'programma', 'review'];

    const completion: Record<string, boolean> = {
      content: hasTitle,
      variants: hasCaption,
      media: hasCaption,
      programma: hasSchedule,
      review: hasSchedule && hashtagsReady,
    };

    const firstPending = steps.find((step) => !completion[step]) ?? 'review';

    stepperItems.forEach((item) => {
      const step = item.dataset.step ?? '';
      item.classList.remove('is-active', 'is-complete', 'is-upcoming');

      if (completion[step]) {
        item.classList.add('is-complete');
        return;
      }

      if (step === firstPending) {
        item.classList.add('is-active');
      } else {
        item.classList.add('is-upcoming');
      }
    });
  };

  const updateIssues = (): void => {
    const issues = composerState.issues;
    const messages =
      issues.length > 0
        ? sprintf(copy.composer.feedback.issuesPrefix, issues.join(' · '))
        : copy.composer.feedback.noIssues;
    issuesOutput.textContent = messages;
    if (issues.length > 0) {
      issuesOutput.classList.add('is-error');
    } else {
      issuesOutput.classList.remove('is-error');
    }
  };

  const updateSubmitState = (): void => {
    const tooltipMessage = composerState.issues.join('\n');
    if (composerState.issues.length > 0) {
      submitButton.disabled = true;
      submitButton.dataset.tooltip = tooltipMessage;
      submitButton.setAttribute('aria-describedby', issuesOutput.id);
    } else {
      submitButton.disabled = false;
      submitButton.removeAttribute('data-tooltip');
      submitButton.removeAttribute('aria-describedby');
    }
  };

  const updateHashtagPreview = (): void => {
    if (composerState.hashtagsFirst) {
      hashtagPreview.removeAttribute('hidden');
      hashtagToggle.setAttribute('aria-expanded', 'true');
    } else {
      hashtagPreview.setAttribute('hidden', '');
      hashtagToggle.setAttribute('aria-expanded', 'false');
    }
  };

  const evaluateComposer = (): void => {
    composerState.title = titleInput.value;
    composerState.caption = captionInput.value;
    composerState.scheduledAt = scheduleInput.value;
    composerState.hashtagsFirst = hashtagToggle.checked;
    composerState.issues = [];
    composerState.notes = [];
    composerInsightStatus.clear();

    let score = 100;

    const title = composerState.title.trim();
    if (title.length < 5) {
      composerState.issues.push(copy.composer.validation.titleShort);
      composerInsightStatus.set('title', false);
      score -= 30;
    } else {
      composerInsightStatus.set('title', true);
    }

    const caption = composerState.caption.trim();
    if (caption.length < 15) {
      composerState.issues.push(copy.composer.validation.captionShort);
      composerInsightStatus.set('caption', false);
      score -= 30;
    } else {
      composerInsightStatus.set('caption', true);
      if (caption.length < 80) {
        composerState.notes.push(copy.composer.validation.captionDetail);
      }
    }

    const scheduledValue = composerState.scheduledAt;
    const scheduledDate = scheduledValue ? new Date(scheduledValue) : null;
    if (!scheduledDate || Number.isNaN(scheduledDate.getTime()) || scheduledDate.getTime() <= Date.now()) {
      composerState.issues.push(copy.composer.validation.scheduleInvalid);
      composerInsightStatus.set('schedule', false);
      score -= 25;
    } else {
      composerInsightStatus.set('schedule', true);
    }

    if (composerState.hashtagsFirst) {
      composerInsightStatus.set('hashtags', true);
    } else {
      composerInsightStatus.set('hashtags', false);
      composerState.notes.push(copy.composer.validation.hashtagsOff);
      score -= 10;
    }

    composerState.score = Math.max(0, Math.min(100, score));

    updatePreflightChip();
    updateStepper();
    updateIssues();
    updateSubmitState();
    updateHashtagPreview();

    if (feedbackOutput.textContent) {
      feedbackOutput.textContent = '';
      feedbackOutput.classList.remove('is-success', 'is-error');
    }
  };

  const closePreflightModal = (): void => {
    modal.setAttribute('hidden', '');
    modal.classList.remove('is-open');
    modal.removeEventListener('keydown', handleModalKeydown);
    preflightChip.setAttribute('aria-expanded', 'false');
    if (preflightModalReturnFocus) {
      preflightModalReturnFocus.focus();
    }
  };

  const handleModalKeydown = (event: KeyboardEvent): void => {
    if (event.key === 'Escape') {
      event.preventDefault();
      closePreflightModal();
      return;
    }

    if (event.key === 'Tab') {
      const focusable = getFocusable(modal);
      if (focusable.length === 0) {
        event.preventDefault();
        return;
      }

      const first = focusable[0];
      const last = focusable[focusable.length - 1];
      if (event.shiftKey) {
        if (document.activeElement === first) {
          event.preventDefault();
          last.focus();
        }
      } else if (document.activeElement === last) {
        event.preventDefault();
        first.focus();
      }
    }
  };

  const openPreflightModal = (): void => {
    preflightModalReturnFocus = (document.activeElement as HTMLElement) ?? null;
    modal.removeAttribute('hidden');
    modal.classList.add('is-open');
    modal.addEventListener('keydown', handleModalKeydown);
    preflightChip.setAttribute('aria-expanded', 'true');
    const focusable = getFocusable(modal);
    (focusable[0] ?? modalClose).focus();
  };

  titleInput.addEventListener('input', evaluateComposer);
  captionInput.addEventListener('input', evaluateComposer);
  scheduleInput.addEventListener('input', evaluateComposer);
  hashtagToggle.addEventListener('change', () => {
    composerState.hashtagsFirst = hashtagToggle.checked;
    evaluateComposer();
  });

  preflightChip.addEventListener('click', (event) => {
    event.preventDefault();
    openPreflightModal();
  });

  modalClose.addEventListener('click', (event) => {
    event.preventDefault();
    closePreflightModal();
  });

  modalOverlay.addEventListener('click', () => {
    closePreflightModal();
  });

  form.addEventListener('submit', (event) => {
    event.preventDefault();
    evaluateComposer();
    if (composerState.issues.length > 0) {
      feedbackOutput.textContent = copy.composer.feedback.blocking;
      feedbackOutput.classList.remove('is-success');
      feedbackOutput.classList.add('is-error');
      return;
    }

    const scheduledDate = composerState.scheduledAt ? new Date(composerState.scheduledAt) : null;
    const timeLabel = scheduledDate ? scheduledDate.toLocaleString() : copy.composer.feedback.fallbackDate;
    feedbackOutput.textContent = sprintf(copy.composer.feedback.scheduled, timeLabel);
    feedbackOutput.classList.remove('is-error');
    feedbackOutput.classList.add('is-success');
  });

  saveDraftButton?.addEventListener('click', (event) => {
    event.preventDefault();
    feedbackOutput.textContent = copy.composer.feedback.draftSaved;
    feedbackOutput.classList.remove('is-error');
    feedbackOutput.classList.add('is-success');
  });

  evaluateComposer();
}

async function renderCalendar(container: HTMLElement): Promise<void> {
  renderCalendarSkeleton(container);

  const params = new URLSearchParams({
    brand: config.brand ?? 'brand-demo',
    channel: activeChannel,
    month: monthKey,
  });

  try {
    const data = await fetchJSON<CalendarResponse>(`${config.restBase}/plans?${params.toString()}`);
    const items = Array.isArray(data.items) ? data.items : [];

    if (items.length === 0) {
      renderCalendarEmpty(container);
      return;
    }

    renderCalendarGrid(container, items);
  } catch (error) {
    const message = (error as Error)?.message ?? __('Unknown error', TEXT_DOMAIN);
    container.innerHTML = `<p class="fp-calendar__error">${escapeHtml(
      sprintf(__('Unable to load the calendar (%s).', TEXT_DOMAIN), message),
    )}</p>`;
  }
}

function renderCalendarSkeleton(container: HTMLElement): void {
  const placeholders = Array.from({ length: 6 })
    .map(
      () =>
        '<div class="fp-calendar__skeleton-card" aria-hidden="true"><div class="fp-calendar__skeleton-bar"></div><div class="fp-calendar__skeleton-bar is-short"></div></div>',
    )
    .join('');

  container.innerHTML = `
    <div class="fp-calendar__skeleton" role="status" aria-live="polite">
      <span class="screen-reader-text">${escapeHtml(__('Loading schedules…', TEXT_DOMAIN))}</span>
      ${placeholders}
    </div>
  `;
}

function renderCalendarEmpty(container: HTMLElement): void {
  container.innerHTML = `
    <div class="fp-calendar__empty" role="alert">
      <h3>${escapeHtml(__('Empty calendar', TEXT_DOMAIN))}</h3>
      <p>${escapeHtml(__('Import schedules from Trello to get started.', TEXT_DOMAIN))}</p>
      <button type="button" class="button button-primary" data-action="calendar-import">${escapeHtml(
        __('Import from Trello', TEXT_DOMAIN),
      )}</button>
    </div>
  `;
}

function collectCalendarItems(plans: CalendarPlanPayload[]): Map<string, CalendarCellItem[]> {
  const buckets = new Map<string, CalendarCellItem[]>();

  plans.forEach((plan) => {
    if (!plan) {
      return;
    }

    const slots = Array.isArray(plan.slots) ? plan.slots : [];
    const title = resolvePlanTitle(plan);
    const status = typeof plan.status === 'string' && plan.status.trim() !== '' ? plan.status : 'draft';

    slots.forEach((slot, index) => {
      if (!slot || typeof slot.scheduled_at !== 'string' || slot.scheduled_at === '') {
        return;
      }

      const scheduledAt = new Date(slot.scheduled_at);
      if (Number.isNaN(scheduledAt.getTime())) {
        return;
      }

      const isoDate = formatDate(scheduledAt);
      const channel = typeof slot.channel === 'string' && slot.channel !== '' ? slot.channel : activeChannel;
      const entry: CalendarCellItem = {
        id: `${plan.id ?? 'plan'}-${index}`,
        title,
        status,
        channel,
        isoDate,
        timeLabel: formatTime(scheduledAt),
        timestamp: scheduledAt.getTime(),
      };

      const bucket = buckets.get(isoDate);
      if (bucket) {
        bucket.push(entry);
      } else {
        buckets.set(isoDate, [entry]);
      }
    });
  });

  buckets.forEach((bucket) => {
    bucket.sort((a, b) => a.timestamp - b.timestamp);
  });

  return buckets;
}

function renderCalendarGrid(container: HTMLElement, plans: CalendarPlanPayload[]): void {
  const itemsByDate = collectCalendarItems(plans);
  const current = new Date(now.getFullYear(), now.getMonth(), 1);
  const daysInMonth = new Date(now.getFullYear(), now.getMonth() + 1, 0).getDate();
  const weekdays = [
    __('Mon', TEXT_DOMAIN),
    __('Tue', TEXT_DOMAIN),
    __('Wed', TEXT_DOMAIN),
    __('Thu', TEXT_DOMAIN),
    __('Fri', TEXT_DOMAIN),
    __('Sat', TEXT_DOMAIN),
    __('Sun', TEXT_DOMAIN),
  ];

  let html = '<table class="fp-publisher-calendar"><thead><tr>';
  html += weekdays.map((day) => `<th scope="col">${day}</th>`).join('');
  html += '</tr></thead><tbody>';

  let day = 1;
  const startOffset = (current.getDay() + 6) % 7;
  for (let row = 0; row < 6 && day <= daysInMonth; row += 1) {
    html += '<tr>';
    for (let col = 0; col < 7; col += 1) {
      const cellIndex = row * 7 + col;
      if (cellIndex < startOffset || day > daysInMonth) {
        html += '<td class="is-empty" aria-disabled="true"></td>';
        continue;
      }

      const cellDate = new Date(now.getFullYear(), now.getMonth(), day);
      const iso = formatDate(cellDate);
      const cellItems = itemsByDate.get(iso) ?? [];
      const itemsMarkup = cellItems
        .map((item) => {
          const tooltip = `${item.title} — ${item.channel} • ${item.timeLabel}`;
          const meta = `${item.channel} · ${item.timeLabel}`;
          return `
            <article class="fp-calendar__item" data-status="${escapeHtml(item.status)}" title="${escapeHtml(tooltip)}">
              <span class="fp-calendar__item-handle" aria-hidden="true">${GRIP_ICON}</span>
              <div class="fp-calendar__item-body">
                <span class="fp-calendar__item-title">${escapeHtml(item.title)}</span>
                <span class="fp-calendar__item-meta">${escapeHtml(meta)}</span>
              </div>
            </article>
          `;
        })
        .join('');

      const actionMarkup = `
        <button
          type="button"
          class="fp-calendar__slot-action"
          data-date="${iso}"
          aria-label="${escapeHtml(
            sprintf(__('Suggest a time for %s', TEXT_DOMAIN), formatHumanDate(cellDate)),
          )}"
        >${escapeHtml(__('Suggest time', TEXT_DOMAIN))}</button>
      `;

      html += `
        <td data-date="${iso}">
          <div class="fp-calendar__cell">
            <span class="fp-calendar-day">${day}</span>
            <div class="fp-calendar__items">${itemsMarkup}</div>
            ${cellItems.length === 0 ? actionMarkup : ''}
          </div>
        </td>
      `;

      day += 1;
    }
    html += '</tr>';
  }

  html += '</tbody></table>';
  container.innerHTML = html;
  applyCalendarDensity(container);
}

function applyCalendarDensity(container: HTMLElement): void {
  const table = container.querySelector<HTMLTableElement>('.fp-publisher-calendar');
  if (!table) {
    return;
  }

  if (calendarDensity === 'compact') {
    table.classList.add('is-compact');
  } else {
    table.classList.remove('is-compact');
  }
}

function syncCalendarDensityButtons(): void {
  const buttons = document.querySelectorAll<HTMLButtonElement>('[data-calendar-density]');
  buttons.forEach((button) => {
    const mode = button.dataset.calendarDensity === 'compact' ? 'compact' : 'comfort';
    const isActive = mode === calendarDensity;
    button.classList.toggle('is-active', isActive);
    button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
  });
}

function setCalendarDensity(mode: 'comfort' | 'compact'): void {
  if (calendarDensity === mode) {
    return;
  }

  calendarDensity = mode;
  const calendarContainer = document.getElementById('fp-calendar');
  if (calendarContainer) {
    applyCalendarDensity(calendarContainer);
  }
  syncCalendarDensityButtons();
}

async function handleSlotSuggestion(button: HTMLButtonElement, date: string): Promise<void> {
  if (!date) {
    return;
  }

  const originalLabel = button.textContent ?? '';
  button.disabled = true;
  button.textContent = __('Loading…', TEXT_DOMAIN);

  try {
    await loadSuggestions(date);
    document.getElementById('fp-besttime-section')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
  } finally {
    button.textContent = originalLabel;
    button.disabled = false;
  }
}

function importCalendarFromTrello(button: HTMLButtonElement): void {
  openTrelloImportModal(button);
}

function openTrelloImportModal(trigger: HTMLElement): void {
  const existing = document.getElementById('fp-trello-modal');
  if (existing) {
    existing.remove();
  }

  const modal = document.createElement('div');
  modal.className = 'fp-modal';
  modal.id = 'fp-trello-modal';
  modal.setAttribute('role', 'dialog');
  modal.setAttribute('aria-modal', 'true');
  modal.setAttribute('aria-labelledby', 'fp-trello-modal-title');

  modal.innerHTML = `
    <div class="fp-modal__backdrop" data-trello-modal-overlay></div>
    <div class="fp-modal__dialog" role="document">
      <header class="fp-modal__header">
        <h2 id="fp-trello-modal-title">${escapeHtml(copy.trello.modalTitle)}</h2>
        <button type="button" class="fp-modal__close" data-trello-modal-close aria-label="${escapeHtml(copy.common.close)}">×</button>
      </header>
      <form id="fp-trello-modal-form" class="fp-trello__form" novalidate>
        <p class="fp-trello__context">${escapeHtml(sprintf(copy.trello.context, config.brand ?? 'brand-demo', activeChannel))}</p>
        <label class="fp-trello__field">
          <span>${escapeHtml(copy.trello.listLabel)}</span>
          <input type="text" name="list_id" placeholder="${escapeHtml(copy.trello.listPlaceholder)}" autocomplete="off" required />
        </label>
        <label class="fp-trello__field">
          <span>${escapeHtml(copy.trello.apiKeyLabel)}</span>
          <input type="text" name="api_key" autocomplete="off" />
        </label>
        <label class="fp-trello__field">
          <span>${escapeHtml(copy.trello.tokenLabel)}</span>
          <input type="text" name="token" autocomplete="off" />
        </label>
        <label class="fp-trello__field">
          <span>${escapeHtml(copy.trello.oauthLabel)}</span>
          <input type="text" name="oauth_token" autocomplete="off" />
          <small class="fp-trello__hint">${escapeHtml(copy.trello.oauthHint)}</small>
        </label>
        <footer class="fp-modal__footer fp-trello__actions">
          <button type="button" class="button" data-trello-modal-close>${escapeHtml(copy.common.close)}</button>
          <button type="button" class="button" data-trello-fetch>${escapeHtml(copy.trello.fetch)}</button>
          <button type="button" class="button button-primary" data-trello-import disabled>${escapeHtml(copy.trello.import)}</button>
        </footer>
        <p id="fp-trello-modal-feedback" class="fp-trello__feedback" role="status" aria-live="polite" hidden></p>
        <div id="fp-trello-modal-cards" class="fp-trello__cards" role="group" aria-live="polite"></div>
      </form>
    </div>
  `;

  document.body.appendChild(modal);

  const returnFocus = trigger instanceof HTMLElement ? trigger : null;
  const closeModal = (): void => {
    modal.remove();
    if (returnFocus) {
      returnFocus.focus();
    }
  };

  modal.querySelectorAll('[data-trello-modal-close], [data-trello-modal-overlay]').forEach((element) => {
    element.addEventListener('click', (event) => {
      event.preventDefault();
      closeModal();
    });
  });

  const form = modal.querySelector<HTMLFormElement>('#fp-trello-modal-form');
  const fetchButton = modal.querySelector<HTMLButtonElement>('[data-trello-fetch]');
  const importButton = modal.querySelector<HTMLButtonElement>('[data-trello-import]');
  const feedback = modal.querySelector<HTMLParagraphElement>('#fp-trello-modal-feedback');
  const cardsContainer = modal.querySelector<HTMLDivElement>('#fp-trello-modal-cards');
  const listInput = modal.querySelector<HTMLInputElement>('input[name="list_id"]');

  listInput?.focus();

  if (!form || !fetchButton || !importButton || !feedback || !cardsContainer) {
    return;
  }

  let cards: TrelloCardSummary[] = [];

  form.addEventListener('submit', (event) => {
    event.preventDefault();
  });

  const resetCards = (): void => {
    cards = [];
    renderTrelloCardsList(cardsContainer, cards);
    importButton.disabled = true;
  };

  fetchButton.addEventListener('click', async (event) => {
    event.preventDefault();
    const credentials = collectTrelloCredentials(form);
    if (!credentials.listId) {
      setTrelloFeedback(feedback, copy.trello.missingList, 'error');
      resetCards();
      return;
    }
    if (!credentials.oauthToken && (credentials.apiKey === '' || credentials.token === '')) {
      setTrelloFeedback(feedback, copy.trello.missingCredentials, 'error');
      resetCards();
      return;
    }

    setTrelloFeedback(feedback, copy.trello.loading, 'info');
    fetchButton.disabled = true;
    importButton.disabled = true;

    try {
      cards = await fetchTrelloCards(credentials);
      renderTrelloCardsList(cardsContainer, cards);
      if (cards.length === 0) {
        setTrelloFeedback(feedback, copy.trello.empty, 'info');
        importButton.disabled = true;
      } else {
        setTrelloFeedback(feedback, '', 'info');
        importButton.disabled = false;
      }
    } catch (error) {
      const message = (error as Error)?.message ?? __('Error', TEXT_DOMAIN);
      setTrelloFeedback(feedback, sprintf(copy.trello.errorLoading, message), 'error');
      resetCards();
    } finally {
      fetchButton.disabled = false;
    }
  });

  importButton.addEventListener('click', async (event) => {
    event.preventDefault();

    const selectedIds = Array.from(
      cardsContainer.querySelectorAll<HTMLInputElement>('input[name="trello-card"]:checked')
    ).map((input) => input.value);

    if (selectedIds.length === 0) {
      setTrelloFeedback(feedback, copy.trello.noSelection, 'error');
      return;
    }

    const credentials = collectTrelloCredentials(form);
    if (!credentials.listId && listInput) {
      credentials.listId = resolveTrelloListId(listInput.value ?? '');
    }

    setTrelloFeedback(feedback, copy.trello.loading, 'info');
    importButton.disabled = true;
    fetchButton.disabled = true;

    try {
      const plans = await importSelectedTrelloCards(credentials, selectedIds);
      setTrelloFeedback(feedback, sprintf(copy.trello.success, plans.length), 'success');
      const calendarContainer = document.getElementById('fp-calendar');
      if (calendarContainer) {
        await renderCalendar(calendarContainer);
      }
      window.setTimeout(() => {
        closeModal();
      }, 1200);
    } catch (error) {
      const message = (error as Error)?.message ?? __('Error', TEXT_DOMAIN);
      setTrelloFeedback(feedback, sprintf(copy.trello.errorImport, message), 'error');
    } finally {
      importButton.disabled = false;
      fetchButton.disabled = false;
    }
  });
}

function collectTrelloCredentials(form: HTMLFormElement): TrelloCredentials {
  const apiKey = (form.querySelector<HTMLInputElement>('input[name="api_key"]')?.value ?? '').trim();
  const token = (form.querySelector<HTMLInputElement>('input[name="token"]')?.value ?? '').trim();
  const oauthToken = (form.querySelector<HTMLInputElement>('input[name="oauth_token"]')?.value ?? '').trim();
  const listValue = (form.querySelector<HTMLInputElement>('input[name="list_id"]')?.value ?? '').trim();

  return {
    apiKey,
    token,
    oauthToken,
    listId: resolveTrelloListId(listValue),
    brand: (config.brand ?? 'brand-demo').trim(),
    channel: activeChannel,
  };
}

function resolveTrelloListId(value: string): string {
  const trimmed = value.trim();
  if (trimmed === '') {
    return '';
  }

  const listMatch = trimmed.match(/\/lists?\/([a-zA-Z0-9]+)/);
  if (listMatch) {
    return listMatch[1];
  }

  const segments = trimmed.split(/[/?#]/).filter((segment) => segment !== '');
  if (segments.length > 0) {
    return segments[segments.length - 1];
  }

  return trimmed;
}

function renderTrelloCardsList(container: HTMLElement, cards: TrelloCardSummary[]): void {
  if (cards.length === 0) {
    container.innerHTML = '';
    return;
  }

  const items = cards
    .map((card) => {
      const dueLabel = formatTrelloDueLabel(card.due ?? null);
      const attachmentsCount = Array.isArray(card.attachments) ? card.attachments.length : 0;
      const attachmentsLabel = attachmentsCount > 0 ? sprintf(copy.trello.attachmentsLabel, attachmentsCount) : '';
      const description = typeof card.description === 'string' && card.description.trim() !== ''
        ? `<p>${escapeHtml(card.description)}</p>`
        : '';
      const metaParts: string[] = [];
      if (dueLabel) {
        metaParts.push(escapeHtml(dueLabel));
      }
      if (attachmentsLabel) {
        metaParts.push(escapeHtml(attachmentsLabel));
      }
      if (card.url) {
        metaParts.push(`<a href="${escapeHtml(card.url)}" target="_blank" rel="noreferrer">${escapeHtml(copy.trello.viewCard)}</a>`);
      }
      const meta = metaParts.length > 0
        ? `<p class="fp-trello__card-meta">${metaParts.join(' · ')}</p>`
        : '';

      return `
        <li class="fp-trello__card">
          <label>
            <input type="checkbox" name="trello-card" value="${escapeHtml(card.id)}" />
            <span class="fp-trello__card-body">
              <strong>${escapeHtml(card.name)}</strong>
              ${meta}
              ${description}
            </span>
          </label>
        </li>
      `;
    })
    .join('');

  container.innerHTML = `
    <p class="fp-trello__hint">${escapeHtml(copy.trello.selectionHint)}</p>
    <ul class="fp-trello__cards-list">${items}</ul>
  `;
}

function formatTrelloDueLabel(due: string | null): string {
  if (!due) {
    return '';
  }

  const date = new Date(due);
  if (Number.isNaN(date.getTime())) {
    return '';
  }

  const datePart = date.toLocaleDateString();
  const timePart = date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

  return `${datePart} · ${timePart}`;
}

function setTrelloFeedback(element: HTMLElement, message: string, tone: 'info' | 'error' | 'success'): void {
  const trimmed = message.trim();
  if (trimmed === '') {
    element.textContent = '';
    element.setAttribute('hidden', '');
    element.removeAttribute('data-tone');
    return;
  }

  element.textContent = trimmed;
  element.dataset.tone = tone;
  element.removeAttribute('hidden');
}

async function fetchTrelloCards(credentials: TrelloCredentials): Promise<TrelloCardSummary[]> {
  const payload: Record<string, unknown> = {
    list_id: credentials.listId,
  };

  if (credentials.apiKey !== '') {
    payload.api_key = credentials.apiKey;
  }
  if (credentials.token !== '') {
    payload.token = credentials.token;
  }
  if (credentials.oauthToken !== '') {
    payload.oauth_token = credentials.oauthToken;
  }

  const data = await fetchJSON<{ cards?: TrelloCardSummary[] }>(`${config.restBase}/ingest/trello/cards`, {
    method: 'POST',
    body: JSON.stringify({ payload }),
  });

  const cards = Array.isArray(data.cards) ? data.cards : [];

  return cards.map((card) => ({
    ...card,
    attachments: Array.isArray(card.attachments) ? card.attachments : [],
    description: typeof card.description === 'string' ? card.description : '',
  }));
}

async function importSelectedTrelloCards(credentials: TrelloCredentials, cardIds: string[]): Promise<CalendarPlanPayload[]> {
  const payload: Record<string, unknown> = {
    brand: credentials.brand,
    channel: credentials.channel,
    list_id: credentials.listId,
    card_ids: cardIds,
  };

  if (credentials.apiKey !== '') {
    payload.api_key = credentials.apiKey;
  }
  if (credentials.token !== '') {
    payload.token = credentials.token;
  }
  if (credentials.oauthToken !== '') {
    payload.oauth_token = credentials.oauthToken;
  }

  const data = await fetchJSON<{ plans?: CalendarPlanPayload[] }>(`${config.restBase}/ingest/trello`, {
    method: 'POST',
    body: JSON.stringify({ payload }),
  });

  return Array.isArray(data.plans) ? data.plans : [];
}

function renderKanban(container: HTMLElement): void {
  const columns = ['draft', 'ready', 'approved', 'scheduled', 'published', 'failed'];
  const columnTitles: Record<string, string> = {
    draft: __('Drafts', TEXT_DOMAIN),
    ready: __('Ready', TEXT_DOMAIN),
    approved: __('Approved', TEXT_DOMAIN),
    scheduled: __('Scheduled', TEXT_DOMAIN),
    published: __('Published', TEXT_DOMAIN),
    failed: __('Failed', TEXT_DOMAIN),
  };

  container.innerHTML = columns
    .map(
      (column) => `
        <section class="fp-kanban-column" data-status="${column}">
          <header class="fp-kanban-column__header">
            <h3>${columnTitles[column] ?? column}</h3>
            <span class="fp-kanban-column__count" data-count="${column}">0</span>
          </header>
          <div class="fp-kanban-column__list" aria-live="polite"></div>
        </section>
      `,
    )
    .join('');
}

function hydrateKanban(): void {
  const list = document.querySelector<HTMLElement>('.fp-kanban-column__list');
  if (!list) {
    return;
  }
  list.innerHTML = `
    <article class="fp-kanban-card">
      <h4>Demo Instagram Reel</h4>
      <p class="fp-kanban-card__meta">${monthKey} · ${activeChannel}</p>
      <button type="button" class="button button-small" data-action="besttime">${escapeHtml(
        __('Suggest time', TEXT_DOMAIN),
      )}</button>
    </article>
  `;
}

function renderComments(container: HTMLElement): void {
  container.innerHTML = `
    <section class="fp-approvals">
      <header class="fp-approvals__header">
        <div>
          <h3>${escapeHtml(__('Approvals workflow', TEXT_DOMAIN))}</h3>
          <p class="fp-approvals__hint">${escapeHtml(
            __('Monitor key decisions and close them with one click.', TEXT_DOMAIN),
          )}</p>
        </div>
        <div class="fp-approvals__actions">
          <button type="button" class="button button-primary" id="fp-approvals-approve">${escapeHtml(
            __('Approve and send', TEXT_DOMAIN),
          )}</button>
          <button type="button" class="button" id="fp-approvals-request">${escapeHtml(
            __('Request changes', TEXT_DOMAIN),
          )}</button>
        </div>
      </header>
      <ol id="fp-approvals-timeline" class="fp-approvals__timeline" aria-live="polite"></ol>
      <div id="fp-approvals-announcer" class="screen-reader-text" aria-live="polite"></div>
    </section>

    <section class="fp-comments__section">
      <header class="fp-comments__header">
        <div>
          <h3>${escapeHtml(__('Plan comments', TEXT_DOMAIN))}</h3>
          <p class="fp-comments__hint" id="fp-comments-hint">${escapeHtml(
            __('Use @ to mention a teammate and notify your feedback.', TEXT_DOMAIN),
          )}</p>
        </div>
        <button type="button" class="button" id="fp-refresh-comments">${escapeHtml(
          __('Refresh', TEXT_DOMAIN),
        )}</button>
      </header>
      <div id="fp-comments-list" class="fp-comments__list" aria-live="polite"></div>
      <form id="fp-comments-form" class="fp-comments__form">
        <label class="fp-comments__field">
          <span class="screen-reader-text">${escapeHtml(__('New comment', TEXT_DOMAIN))}</span>
          <textarea
            name="body"
            rows="3"
            required
            placeholder="${escapeHtml(__('Write a comment…', TEXT_DOMAIN))}"
            aria-autocomplete="list"
            aria-expanded="false"
            aria-owns="fp-mentions-list"
            aria-describedby="fp-comments-hint"
          ></textarea>
        </label>
        <ul
          id="fp-mentions-list"
          class="fp-comments__mentions"
          role="listbox"
          aria-label="${escapeHtml(__('Mention suggestions', TEXT_DOMAIN))}"
          hidden
        ></ul>
        <div class="fp-comments__submit">
          <span class="fp-comments__hint">${escapeHtml(
            __('Comments notify the editorial team.', TEXT_DOMAIN),
          )}</span>
          <button type="submit" class="button button-primary">${escapeHtml(__('Send', TEXT_DOMAIN))}</button>
        </div>
        <div id="fp-comments-announcer" class="screen-reader-text" aria-live="polite"></div>
      </form>
    </section>
  `;
}

function approvalTone(status: ApprovalEvent['status']): 'positive' | 'neutral' | 'warning' {
  return APPROVAL_STATUS_TONES[status] ?? 'neutral';
}

function renderApprovalEvent(event: ApprovalEvent): string {
  const tone = approvalTone(event.status);
  const badgeLabel = APPROVAL_STATUS_LABELS[event.status] ?? event.status;
  const note = event.note ? `<p class="fp-approvals__note">${escapeHtml(event.note)}</p>` : '';

  return `
    <li class="fp-approvals__item">
      <span class="fp-approvals__avatar" aria-hidden="true">${initialsForName(event.actor.display_name)}</span>
      <div class="fp-approvals__content">
        <header class="fp-approvals__meta">
          <strong>${escapeHtml(event.actor.display_name)}</strong>
          <time>${new Date(event.occurred_at).toLocaleString()}</time>
        </header>
        <span class="fp-approvals__badge" data-tone="${tone}">${badgeLabel}</span>
        ${note}
      </div>
    </li>
  `;
}

async function loadApprovalsTimeline(): Promise<void> {
  const timeline = document.getElementById('fp-approvals-timeline');
  if (!timeline) {
    return;
  }

  timeline.innerHTML = `<li class="fp-approvals__placeholder">${escapeHtml(__('Loading workflow…', TEXT_DOMAIN))}</li>`;
  try {
    const data = await fetchJSON<{ items: ApprovalEvent[] }>(`${config.restBase}/plans/1/approvals`);
    if (!data.items.length) {
      timeline.innerHTML = `<li class="fp-approvals__placeholder">${escapeHtml(
        __('No activity recorded in the workflow.', TEXT_DOMAIN),
      )}</li>`;
      announceApprovalsUpdate(__('No activity in the approvals workflow.', TEXT_DOMAIN));
      return;
    }

    timeline.innerHTML = data.items.map(renderApprovalEvent).join('');
    announceApprovalsUpdate(__('Approvals workflow updated.', TEXT_DOMAIN));
  } catch (error) {
    timeline.innerHTML = `<li class="fp-approvals__placeholder fp-approvals__placeholder--error">${escapeHtml(
      sprintf(__('Unable to fetch the workflow (%s).', TEXT_DOMAIN), (error as Error).message),
    )}</li>`;
    announceApprovalsUpdate(__('Unable to refresh the approvals workflow.', TEXT_DOMAIN));
  }
}

function mentionOptionId(index: number): string {
  const suggestion = mentionState.suggestions[index];
  return `fp-mention-option-${suggestion?.id ?? index}`;
}

function resetMentionState(): void {
  mentionState.anchor = -1;
  mentionState.query = '';
  mentionState.suggestions = [];
  mentionState.activeIndex = -1;
}

function hideMentionSuggestions(): void {
  const { list, textarea } = mentionState;
  if (list) {
    list.hidden = true;
    list.innerHTML = '';
  }
  textarea?.setAttribute('aria-expanded', 'false');
  textarea?.removeAttribute('aria-activedescendant');
  if (mentionFetchTimeout) {
    window.clearTimeout(mentionFetchTimeout);
    mentionFetchTimeout = undefined;
  }
  resetMentionState();
}

function updateActiveMention(): void {
  const { list, activeIndex, textarea } = mentionState;
  if (!list) {
    return;
  }

  const items = Array.from(list.querySelectorAll<HTMLLIElement>('[data-mention-index]'));
  items.forEach((item) => {
    const index = Number(item.dataset.mentionIndex ?? '-1');
    const isActive = index === activeIndex;
    item.classList.toggle('is-active', isActive);
    item.setAttribute('aria-selected', isActive ? 'true' : 'false');
  });

  if (textarea) {
    if (activeIndex >= 0 && mentionState.suggestions[activeIndex]) {
      textarea.setAttribute('aria-activedescendant', mentionOptionId(activeIndex));
    } else {
      textarea.removeAttribute('aria-activedescendant');
    }
  }
}

function renderMentionSuggestionsList(): void {
  const { list, suggestions, textarea, activeIndex } = mentionState;
  if (!list) {
    return;
  }

  if (!suggestions.length) {
    list.innerHTML = `<li class="fp-comments__mention fp-comments__mention--empty" role="option" aria-disabled="true">${escapeHtml(
      __('No user found.', TEXT_DOMAIN),
    )}</li>`;
    list.hidden = false;
    textarea?.setAttribute('aria-expanded', 'true');
    textarea?.removeAttribute('aria-activedescendant');
    return;
  }

  list.innerHTML = suggestions
    .map((suggestion, index) => {
      const description = suggestion.description ? `<span>${escapeHtml(suggestion.description)}</span>` : '';
      return `
        <li
          class="fp-comments__mention${activeIndex === index ? ' is-active' : ''}"
          data-mention-index="${index}"
          role="option"
          id="${mentionOptionId(index)}"
          aria-selected="${activeIndex === index ? 'true' : 'false'}"
        >
          <strong>${escapeHtml(suggestion.name)}</strong>
          ${description}
        </li>
      `;
    })
    .join('');

  list.hidden = false;
  textarea?.setAttribute('aria-expanded', 'true');
  updateActiveMention();
}

async function fetchMentionSuggestions(query: string): Promise<MentionSuggestion[]> {
  const endpoint = `/wp-json/wp/v2/users?per_page=5&search=${encodeURIComponent(query)}`;
  const response = await fetch(endpoint, {
    credentials: 'same-origin',
    headers: {
      'X-WP-Nonce': config.nonce,
    },
  });

  if (!response.ok) {
    throw new Error(`HTTP ${response.status}`);
  }

  const payload = (await response.json()) as WPUser[];
  return payload.map((user) => ({
    id: user.id,
    name: user.name,
    slug: user.slug,
    description: user.description,
  }));
}

async function requestMentionSuggestions(query: string): Promise<void> {
  const { list, textarea } = mentionState;
  if (!list || !textarea) {
    return;
  }

  const requestId = ++mentionRequestId;
  list.hidden = false;
  list.innerHTML = `<li class="fp-comments__mention fp-comments__mention--loading" role="option" aria-disabled="true">${escapeHtml(
    __('Searching users…', TEXT_DOMAIN),
  )}</li>`;
  textarea.setAttribute('aria-expanded', 'true');

  try {
    const suggestions = await fetchMentionSuggestions(query);
    if (requestId !== mentionRequestId) {
      return;
    }
    mentionState.suggestions = suggestions;
    mentionState.activeIndex = suggestions.length ? 0 : -1;
    renderMentionSuggestionsList();
    if (suggestions.length) {
      announceCommentUpdate(
        sprintf(__('%d suggestions found.', TEXT_DOMAIN), suggestions.length),
      );
    }
  } catch (error) {
    if (requestId !== mentionRequestId) {
      return;
    }
    list.innerHTML = `<li class="fp-comments__mention fp-comments__mention--error" role="option" aria-disabled="true">${escapeHtml(
      sprintf(__('Error while searching (%s).', TEXT_DOMAIN), (error as Error).message),
    )}</li>`;
    announceCommentUpdate(__('Unable to fetch mentions.', TEXT_DOMAIN));
  }
}

function applyMentionSuggestion(index: number): void {
  const suggestion = mentionState.suggestions[index];
  const textarea = mentionState.textarea;
  if (!suggestion || !textarea) {
    return;
  }

  const caret = textarea.selectionStart ?? textarea.value.length;
  const before = textarea.value.slice(0, mentionState.anchor);
  const after = textarea.value.slice(caret);
  const handle = suggestion.slug || suggestion.name.replace(/\s+/g, '').toLowerCase();
  const mentionText = `@${handle}`;

  textarea.value = `${before}${mentionText} ${after.replace(/^\s*/, '')}`;
  const newCaret = before.length + mentionText.length + 1;
  textarea.setSelectionRange(newCaret, newCaret);
  announceCommentUpdate(sprintf(__('%s added to the comment.', TEXT_DOMAIN), suggestion.name));
  hideMentionSuggestions();
}

function handleMentionInput(event: Event): void {
  const textarea = event.currentTarget as HTMLTextAreaElement;
  mentionState.textarea = textarea;
  const list = mentionState.list;
  if (!list) {
    return;
  }

  const caret = textarea.selectionStart ?? textarea.value.length;
  const value = textarea.value;
  const uptoCaret = value.slice(0, caret);
  const triggerIndex = uptoCaret.lastIndexOf('@');

  if (triggerIndex === -1) {
    hideMentionSuggestions();
    return;
  }

  if (triggerIndex > 0) {
    const prevChar = uptoCaret.charAt(triggerIndex - 1);
    if (prevChar && /[\w@]/.test(prevChar)) {
      hideMentionSuggestions();
      return;
    }
  }

  const query = uptoCaret.slice(triggerIndex + 1);
  if (!/^[\w._-]*$/.test(query)) {
    hideMentionSuggestions();
    return;
  }

  mentionState.anchor = triggerIndex;

  if (query.length < 2) {
    mentionState.query = query;
    mentionState.suggestions = [];
    mentionState.activeIndex = -1;
    list.hidden = false;
    list.innerHTML = `<li class="fp-comments__mention fp-comments__mention--hint" role="option" aria-disabled="true">${escapeHtml(
      __('Type at least two characters to search for a user.', TEXT_DOMAIN),
    )}</li>`;
    textarea.setAttribute('aria-expanded', 'true');
    textarea.removeAttribute('aria-activedescendant');
    return;
  }

  if (query === mentionState.query && !list.hidden) {
    return;
  }

  mentionState.query = query;
  if (mentionFetchTimeout) {
    window.clearTimeout(mentionFetchTimeout);
  }
  mentionFetchTimeout = window.setTimeout(() => {
    void requestMentionSuggestions(query);
  }, 180);
}

function handleMentionKeyDown(event: KeyboardEvent): void {
  const { list, suggestions } = mentionState;
  if (!list || list.hidden) {
    return;
  }

  if (event.key === 'ArrowDown') {
    if (!suggestions.length) {
      return;
    }
    event.preventDefault();
    mentionState.activeIndex = (mentionState.activeIndex + 1) % suggestions.length;
    updateActiveMention();
    return;
  }

  if (event.key === 'ArrowUp') {
    if (!suggestions.length) {
      return;
    }
    event.preventDefault();
    mentionState.activeIndex =
      (mentionState.activeIndex - 1 + suggestions.length) % suggestions.length;
    updateActiveMention();
    return;
  }

  if (event.key === 'Enter' || event.key === 'Tab') {
    if (mentionState.activeIndex >= 0 && suggestions[mentionState.activeIndex]) {
      event.preventDefault();
      applyMentionSuggestion(mentionState.activeIndex);
    }
    return;
  }

  if (event.key === 'Escape') {
    event.preventDefault();
    hideMentionSuggestions();
  }
}

function initMentionAutocomplete(textarea: HTMLTextAreaElement, list: HTMLUListElement): void {
  mentionState.textarea = textarea;
  mentionState.list = list;
  textarea.addEventListener('input', handleMentionInput);
  textarea.addEventListener('keydown', handleMentionKeyDown);
  textarea.addEventListener('blur', () => {
    window.setTimeout(() => {
      hideMentionSuggestions();
    }, 120);
  });

  list.addEventListener('mousedown', (event) => {
    event.preventDefault();
  });

  list.addEventListener('click', (event) => {
    const target = (event.target as HTMLElement).closest<HTMLLIElement>('[data-mention-index]');
    if (!target) {
      return;
    }
    const index = Number(target.dataset.mentionIndex ?? '-1');
    if (Number.isNaN(index)) {
      return;
    }
    applyMentionSuggestion(index);
  });
}

async function handleApprovalAction(action: 'approved' | 'changes_requested'): Promise<void> {
  const approveBtn = document.getElementById('fp-approvals-approve') as HTMLButtonElement | null;
  const requestBtn = document.getElementById('fp-approvals-request') as HTMLButtonElement | null;
  approveBtn?.setAttribute('disabled', 'true');
  requestBtn?.setAttribute('disabled', 'true');

  try {
    await fetchJSON(`${config.restBase}/plans/1/approvals`, {
      method: 'POST',
      body: JSON.stringify({ status: action }),
    });
    await loadApprovalsTimeline();
    if (action === 'approved') {
      announceApprovalsUpdate(__('Plan approved and sent to the team.', TEXT_DOMAIN));
    } else {
      announceApprovalsUpdate(__('Change request sent to the authors.', TEXT_DOMAIN));
    }
  } catch (error) {
    announceApprovalsUpdate(
      sprintf(__('Error while updating the workflow: %s', TEXT_DOMAIN), (error as Error).message),
    );
  } finally {
    approveBtn?.removeAttribute('disabled');
    requestBtn?.removeAttribute('disabled');
  }
}

function renderSuggestions(
  container: HTMLElement,
  suggestions: Suggestion[],
  contextLabel?: string,
): void {
  if (suggestions.length === 0) {
    const emptyLabel = contextLabel
      ? sprintf(__('No suggestions available for %s.', TEXT_DOMAIN), contextLabel)
      : __('No suggestions available for the selected period.', TEXT_DOMAIN);
    container.innerHTML = `<p class="fp-besttime__empty">${escapeHtml(emptyLabel)}</p>`;
    return;
  }

  const contextMarkup = contextLabel
    ? `<p class="fp-besttime__context">${escapeHtml(
        sprintf(__('Suggestions for %s', TEXT_DOMAIN), contextLabel),
      )}</p>`
    : '';

  const itemsMarkup = suggestions
    .slice(0, 6)
    .map(
      (item) => `
        <article class="fp-besttime__item">
          <h4>${new Date(item.datetime).toLocaleString()}</h4>
          <p>${item.reason}</p>
          <span class="fp-besttime__score">${escapeHtml(
            sprintf(__('Score %d', TEXT_DOMAIN), item.score),
          )}</span>
        </article>
      `,
    )
    .join('');

  container.innerHTML = `${contextMarkup}${itemsMarkup}`;
}

async function sendRequest(url: string, options: RequestInit = {}): Promise<Response> {
  const headers = new Headers(options.headers ?? {});
  if (!headers.has('Content-Type')) {
    headers.set('Content-Type', 'application/json');
  }
  if (!headers.has('X-WP-Nonce')) {
    headers.set('X-WP-Nonce', config.nonce);
  }

  const response = await fetch(url, {
    credentials: 'same-origin',
    ...options,
    headers,
  });

  if (!response.ok) {
    throw new Error(`HTTP ${response.status}`);
  }

  return response;
}

async function fetchJSON<T>(url: string, options: RequestInit = {}): Promise<T> {
  const response = await sendRequest(url, options);
  return response.json() as Promise<T>;
}

async function loadSuggestions(day?: string): Promise<void> {
  const container = document.getElementById('fp-besttime-results');
  if (!container) {
    return;
  }

  container.innerHTML = `<p class="fp-besttime__loading">${escapeHtml(__('Calculating suggestions…', TEXT_DOMAIN))}</p>`;

  const params = new URLSearchParams({
    brand: config.brand ?? 'brand-demo',
    channel: activeChannel,
    month: monthKey,
  });

  let contextLabel: string | undefined;
  if (day) {
    params.set('day', day);
    const parsed = new Date(day);
    if (!Number.isNaN(parsed.getTime())) {
      contextLabel = formatHumanDate(parsed);
    }
  }

  try {
    const data = await fetchJSON<{ suggestions: Suggestion[] }>(
      `${config.restBase}/besttime?${params.toString()}`,
    );
    renderSuggestions(container, data.suggestions, contextLabel);
  } catch (error) {
    const message = (error as Error)?.message ?? __('Unknown error', TEXT_DOMAIN);
    container.innerHTML = `<p class="fp-besttime__error">${escapeHtml(
      sprintf(__('Unable to fetch suggestions (%s).', TEXT_DOMAIN), message),
    )}</p>`;
  }
}

async function loadComments(): Promise<void> {
  const list = document.getElementById('fp-comments-list');
  if (!list) {
    return;
  }

  list.innerHTML = `<p class="fp-comments__loading">${escapeHtml(__('Loading comments…', TEXT_DOMAIN))}</p>`;
  try {
    const data = await fetchJSON<{ items: CommentItem[] }>(`${config.restBase}/plans/1/comments`);
    if (!data.items.length) {
      list.innerHTML = `<p class="fp-comments__empty">${escapeHtml(__('No comments available.', TEXT_DOMAIN))}</p>`;
      announceCommentUpdate(__('No comments available.', TEXT_DOMAIN));
      return;
    }

    list.innerHTML = data.items
      .map(
        (item) => `
          <article class="fp-comments__item">
            <header>
              <strong>${item.author.display_name}</strong>
              <time>${new Date(item.created_at).toLocaleString()}</time>
            </header>
            <p>${formatCommentBody(item.body)}</p>
          </article>
        `,
      )
      .join('');
    announceCommentUpdate(__('Comments updated.', TEXT_DOMAIN));
  } catch (error) {
    list.innerHTML = `<p class="fp-comments__error">${escapeHtml(
      sprintf(__('Unable to load comments (%s).', TEXT_DOMAIN), (error as Error).message),
    )}</p>`;
    announceCommentUpdate(__('Error while loading comments.', TEXT_DOMAIN));
  }
}

async function loadShortLinks(): Promise<void> {
  const table = document.getElementById('fp-shortlink-table');
  const skeleton = document.getElementById('fp-shortlink-skeleton');
  if (!table || !skeleton) {
    return;
  }

  table.setAttribute('data-loading', 'true');
  table.setAttribute('aria-busy', 'true');
  skeleton.removeAttribute('hidden');
  setShortLinkFeedback(copy.shortlinks.feedback.loading, 'muted');

  try {
    const data = await fetchJSON<{ items?: ShortLink[] }>(`${config.restBase}/links`);
    shortLinks = Array.isArray(data.items) ? data.items : [];
    renderShortLinkTable();
    if (shortLinks.length === 0) {
      setShortLinkFeedback(copy.shortlinks.feedback.empty, 'muted');
    } else {
      setShortLinkFeedback(null);
    }
  } catch (error) {
    shortLinks = [];
    renderShortLinkTable();
    setShortLinkFeedback(
      sprintf(__('Unable to load links (%s).', TEXT_DOMAIN), (error as Error).message),
      'error',
    );
  } finally {
    table.removeAttribute('data-loading');
    table.setAttribute('aria-busy', 'false');
    skeleton.setAttribute('hidden', '');
  }
}

function setShortLinkFeedback(message: string | null, tone: 'muted' | 'success' | 'error' = 'muted'): void {
  const feedback = document.getElementById('fp-shortlink-feedback');
  if (!feedback) {
    return;
  }

  if (!message) {
    feedback.textContent = '';
    feedback.setAttribute('hidden', '');
    feedback.removeAttribute('data-tone');
    return;
  }

  feedback.textContent = message;
  feedback.dataset.tone = tone;
  feedback.removeAttribute('hidden');
}

function renderShortLinkTable(): void {
  const body = document.getElementById('fp-shortlink-rows');
  const empty = document.getElementById('fp-shortlink-empty');
  const table = document.getElementById('fp-shortlink-table');
  if (!body || !empty || !table) {
    return;
  }

  if (shortLinks.length === 0) {
    body.innerHTML = '';
    empty.textContent = copy.shortlinks.empty;
    empty.removeAttribute('hidden');
    table.setAttribute('data-empty', 'true');
    return;
  }

  empty.setAttribute('hidden', '');
  table.removeAttribute('data-empty');

  const formatter = new Intl.NumberFormat();
  body.innerHTML = shortLinks
    .map((link) => {
      const slug = escapeHtml(link.slug);
      const target = escapeHtml(link.target_url);
      const truncatedTarget = escapeHtml(truncateText(link.target_url));
      const goUrl = escapeHtml(buildShortLinkUrl(link.slug));
      const clicks = formatter.format(Math.max(0, Number.isFinite(link.clicks) ? link.clicks : 0));
      const lastClick = escapeHtml(formatLastClickAt(link.last_click_at));
      const toggleBase = toDomId('fp-shortlink-menu', link.slug);
      const toggleId = `${toggleBase}-toggle`;
      const panelId = `${toggleBase}-panel`;
      const menuLabel = escapeHtml(sprintf(copy.shortlinks.menuLabel, link.slug));
      const actionOpen = escapeHtml(copy.shortlinks.actions.open);
      const actionCopy = escapeHtml(copy.shortlinks.actions.copy);
      const actionEdit = escapeHtml(copy.shortlinks.actions.edit);
      const actionDisable = escapeHtml(copy.shortlinks.actions.disable);

      return `
        <tr data-slug="${slug}">
          <th scope="row"><code class="fp-shortlink__slug">${slug}</code></th>
          <td><span class="fp-shortlink__target" title="${target}">${truncatedTarget}</span></td>
          <td class="fp-shortlink__metric">${clicks}</td>
          <td class="fp-shortlink__metric">${lastClick}</td>
          <td class="fp-shortlink__actions">
            <div class="fp-shortlink__menu">
              <button
                type="button"
                class="fp-shortlink__menu-toggle"
                id="${toggleId}"
                data-shortlink-menu
                data-slug="${slug}"
                data-url="${goUrl}"
                aria-haspopup="true"
                aria-expanded="false"
                aria-controls="${panelId}"
              >
                <span class="screen-reader-text">${menuLabel}</span>
                <span aria-hidden="true" class="fp-shortlink__menu-icon">⋮</span>
              </button>
              <div class="fp-shortlink__menu-panel" role="menu" id="${panelId}" aria-labelledby="${toggleId}" hidden>
                <button type="button" role="menuitem" data-shortlink-action="open" data-slug="${slug}" data-url="${goUrl}" data-target="${target}">${actionOpen}</button>
                <button type="button" role="menuitem" data-shortlink-action="copy" data-slug="${slug}" data-url="${goUrl}">${actionCopy}</button>
                <button type="button" role="menuitem" data-shortlink-action="edit" data-slug="${slug}">${actionEdit}</button>
                <button type="button" role="menuitem" data-shortlink-action="disable" data-slug="${slug}">${actionDisable}</button>
              </div>
            </div>
          </td>
        </tr>
      `;
    })
    .join('');
}

function getFocusableElements(root: HTMLElement): HTMLElement[] {
  return Array.from(
    root.querySelectorAll<HTMLElement>(
      'a[href], button:not([disabled]), textarea, input, select, [tabindex]:not([tabindex="-1"])',
    ),
  ).filter((node) => node.offsetParent !== null);
}

function closeShortLinkMenu(): void {
  if (!activeShortLinkMenu) {
    return;
  }

  const panel = activeShortLinkMenu.nextElementSibling as HTMLElement | null;
  activeShortLinkMenu.classList.remove('is-open');
  activeShortLinkMenu.setAttribute('aria-expanded', 'false');
  panel?.setAttribute('hidden', '');
  activeShortLinkMenu = null;
}

function toggleShortLinkMenu(button: HTMLButtonElement): void {
  if (activeShortLinkMenu === button) {
    closeShortLinkMenu();
    return;
  }

  closeShortLinkMenu();
  const panel = button.nextElementSibling as HTMLElement | null;
  if (!panel) {
    return;
  }

  button.classList.add('is-open');
  button.setAttribute('aria-expanded', 'true');
  panel.removeAttribute('hidden');
  activeShortLinkMenu = button;

  const firstItem = panel.querySelector<HTMLElement>('[role="menuitem"]');
  firstItem?.focus();
}

async function copyToClipboard(value: string): Promise<boolean> {
  try {
    if (navigator.clipboard?.writeText) {
      await navigator.clipboard.writeText(value);
      return true;
    }
  } catch (error) {
    console.warn('Clipboard API non disponibile', error);
  }

  try {
    const textarea = document.createElement('textarea');
    textarea.value = value;
    textarea.setAttribute('readonly', 'true');
    textarea.style.position = 'absolute';
    textarea.style.left = '-9999px';
    document.body.appendChild(textarea);
    textarea.select();
    const result = document.execCommand('copy');
    document.body.removeChild(textarea);
    return result;
  } catch (error) {
    console.warn('Fallback clipboard copy fallito', error);
    return false;
  }
}

async function handleShortLinkAction(button: HTMLButtonElement): Promise<void> {
  const action = button.dataset.shortlinkAction;
  const slug = button.dataset.slug ?? '';

  if (!action || !slug) {
    return;
  }

  if (action === 'open') {
    const url = button.dataset.url ?? buildShortLinkUrl(slug);
    const newWindow = window.open(url, '_blank', 'noopener');
    if (newWindow) {
      newWindow.opener = null;
    }
    setShortLinkFeedback(sprintf(copy.shortlinks.feedback.open, slug), 'success');
    return;
  }

  if (action === 'copy') {
    const url = button.dataset.url ?? buildShortLinkUrl(slug);
    const copied = await copyToClipboard(url);
    if (copied) {
      setShortLinkFeedback(copy.shortlinks.feedback.copySuccess, 'success');
    } else {
      setShortLinkFeedback(copy.shortlinks.feedback.copyError, 'error');
    }
    return;
  }

  if (action === 'edit') {
    const link = shortLinks.find((item) => item.slug === slug);
    openShortLinkModal('edit', link);
    return;
  }

  if (action === 'disable') {
    await disableShortLink(slug);
  }
}

async function disableShortLink(slug: string): Promise<void> {
  if (!slug) {
    return;
  }

  setShortLinkFeedback(copy.shortlinks.feedback.disabling, 'muted');

  try {
    await sendRequest(`${config.restBase}/links/${encodeURIComponent(slug)}`, {
      method: 'DELETE',
    });
    shortLinks = shortLinks.filter((item) => item.slug !== slug);
    renderShortLinkTable();
    if (shortLinks.length === 0) {
      setShortLinkFeedback(copy.shortlinks.feedback.disabledEmpty, 'success');
    } else {
      setShortLinkFeedback(copy.shortlinks.feedback.disabled, 'success');
    }
  } catch (error) {
    setShortLinkFeedback(
      sprintf(copy.shortlinks.errors.disable, (error as Error).message),
      'error',
    );
  }
}

function getShortLinkModalElements(): {
  modal: HTMLElement;
  form: HTMLFormElement;
  title: HTMLElement;
  slugInput: HTMLInputElement;
  targetInput: HTMLInputElement;
  preview: HTMLElement;
  error: HTMLElement;
  submit: HTMLButtonElement;
  cancel: HTMLButtonElement;
  close: HTMLButtonElement;
  overlay: HTMLElement;
} | null {
  const modal = document.getElementById('fp-shortlink-modal');
  if (!(modal instanceof HTMLElement)) {
    return null;
  }

  const form = modal.querySelector<HTMLFormElement>('#fp-shortlink-modal-form');
  const title = modal.querySelector<HTMLElement>('#fp-shortlink-modal-title');
  const slugInput = modal.querySelector<HTMLInputElement>('#fp-shortlink-input-slug');
  const targetInput = modal.querySelector<HTMLInputElement>('#fp-shortlink-input-target');
  const preview = modal.querySelector<HTMLElement>('#fp-shortlink-modal-preview');
  const error = modal.querySelector<HTMLElement>('#fp-shortlink-modal-error');
  const submit = modal.querySelector<HTMLButtonElement>('#fp-shortlink-modal-submit');
  const cancel = modal.querySelector<HTMLButtonElement>('#fp-shortlink-modal-cancel');
  const close = modal.querySelector<HTMLButtonElement>('[data-shortlink-modal-close]');
  const overlay = modal.querySelector<HTMLElement>('[data-shortlink-modal-overlay]');

  if (!form || !title || !slugInput || !targetInput || !preview || !error || !submit || !cancel || !close || !overlay) {
    return null;
  }

  return {
    modal,
    form,
    title,
    slugInput,
    targetInput,
    preview,
    error,
    submit,
    cancel,
    close,
    overlay,
  };
}

function updateShortLinkModalPreview(): void {
  const elements = getShortLinkModalElements();
  if (!elements) {
    return;
  }

  const { slugInput, targetInput, preview, error, submit } = elements;
  const slugValue = slugInput.value.trim();
  const targetValue = targetInput.value.trim();

  const messages: string[] = [];
  if (!slugValue) {
    messages.push(copy.shortlinks.validation.slugMissing);
  } else if (!/^[a-z0-9-]+$/i.test(slugValue)) {
    messages.push(copy.shortlinks.validation.slugFormat);
  }

  let destination: URL | null = null;
  if (!targetValue) {
    messages.push(copy.shortlinks.validation.targetMissing);
  } else {
    try {
      destination = new URL(targetValue);
    } catch {
      messages.push(copy.shortlinks.validation.targetInvalid);
    }
  }

  let utmPreview = '';
  if (destination) {
    const utmUrl = new URL(destination.toString());
    utmUrl.searchParams.set('utm_source', 'fp-publisher');
    utmUrl.searchParams.set('utm_medium', 'social');
    utmUrl.searchParams.set('utm_campaign', slugValue || 'shortlink');
    utmPreview = utmUrl.toString();
  }

  if (messages.length > 0) {
    error.textContent = messages.join(' ');
    error.removeAttribute('hidden');
    submit.disabled = true;
  } else {
    error.textContent = '';
    error.setAttribute('hidden', '');
    submit.disabled = false;
  }

  const goUrl = slugValue ? buildShortLinkUrl(slugValue) : buildShortLinkUrl('preview');
  const shortlinkLabel = escapeHtml(copy.shortlinks.preview.shortlinkLabel);
  const utmLabel = escapeHtml(copy.shortlinks.preview.utmLabel);
  const waitingMessage = escapeHtml(copy.shortlinks.preview.waiting);
  const previewDefault = escapeHtml(copy.shortlinks.modal.previewDefault);

  const previewLines: string[] = [`<p><strong>${shortlinkLabel}</strong> <code>${escapeHtml(goUrl)}</code></p>`];

  if (utmPreview) {
    previewLines.push(
      `<p><strong>${utmLabel}</strong> <span title="${escapeHtml(utmPreview)}">${escapeHtml(truncateText(
        utmPreview,
        96,
      ))}</span></p>`,
    );
  } else if (targetValue) {
    previewLines.push(`<p>${waitingMessage}</p>`);
  } else {
    previewLines.push(`<p>${previewDefault}</p>`);
  }

  preview.innerHTML = previewLines.join('');
}

function closeShortLinkModal(): void {
  const elements = getShortLinkModalElements();
  if (!elements) {
    return;
  }

  const { modal, form, error, preview } = elements;
  modal.setAttribute('hidden', '');
  modal.classList.remove('is-open');
  if (shortLinkModalKeydownHandler) {
    modal.removeEventListener('keydown', shortLinkModalKeydownHandler);
    shortLinkModalKeydownHandler = null;
  }

  form.reset();
  error.textContent = '';
  error.setAttribute('hidden', '');
  preview.innerHTML = `<p>${escapeHtml(copy.shortlinks.modal.previewDefault)}</p>`;

  if (shortLinkModalReturnFocus) {
    shortLinkModalReturnFocus.focus();
  }
  shortLinkModalReturnFocus = null;
  shortLinkEditingSlug = null;
}

function openShortLinkModal(mode: 'create' | 'edit', link?: ShortLink): void {
  const elements = getShortLinkModalElements();
  if (!elements) {
    return;
  }

  const { modal, title, slugInput, targetInput, submit } = elements;
  shortLinkModalReturnFocus = (document.activeElement as HTMLElement) ?? null;
  modal.dataset.mode = mode;
  title.textContent = mode === 'edit' ? copy.shortlinks.modal.editTitle : copy.shortlinks.modal.createTitle;
  submit.textContent = mode === 'edit' ? copy.shortlinks.modal.update : copy.shortlinks.modal.create;
  slugInput.value = link?.slug ?? '';
  targetInput.value = link?.target_url ?? '';
  shortLinkEditingSlug = link?.slug ?? null;

  updateShortLinkModalPreview();

  modal.removeAttribute('hidden');
  modal.classList.add('is-open');

  const handleKeydown = (event: KeyboardEvent): void => {
    if (event.key === 'Escape') {
      event.preventDefault();
      closeShortLinkModal();
      return;
    }

    if (event.key === 'Tab') {
      const focusable = getFocusableElements(modal);
      if (focusable.length === 0) {
        return;
      }

      const first = focusable[0];
      const last = focusable[focusable.length - 1];
      if (event.shiftKey) {
        if (document.activeElement === first) {
          event.preventDefault();
          last.focus();
        }
      } else if (document.activeElement === last) {
        event.preventDefault();
        first.focus();
      }
    }
  };

  shortLinkModalKeydownHandler = handleKeydown;
  modal.addEventListener('keydown', handleKeydown);

  slugInput.focus();
}

async function handleShortLinkModalSubmit(event: Event): Promise<void> {
  event.preventDefault();
  const elements = getShortLinkModalElements();
  if (!elements) {
    return;
  }

  const { modal, slugInput, targetInput, error, submit } = elements;
  updateShortLinkModalPreview();

  if (submit.disabled) {
    return;
  }

  const slugValue = slugInput.value.trim();
  const targetValue = targetInput.value.trim();
  const mode = modal.dataset.mode === 'edit' ? 'edit' : 'create';

  submit.disabled = true;
  submit.setAttribute('aria-busy', 'true');

  try {
    const payload = { slug: slugValue, target_url: targetValue };
    if (mode === 'edit') {
      const endpoint = `${config.restBase}/links/${encodeURIComponent(shortLinkEditingSlug ?? slugValue)}`;
      await fetchJSON<{ link: ShortLink }>(endpoint, {
        method: 'PUT',
        body: JSON.stringify(payload),
      });
      setShortLinkFeedback(copy.shortlinks.feedback.updated, 'success');
    } else {
      await fetchJSON<{ link: ShortLink }>(`${config.restBase}/links`, {
        method: 'POST',
        body: JSON.stringify(payload),
      });
      setShortLinkFeedback(copy.shortlinks.feedback.created, 'success');
    }

    await loadShortLinks();
    closeShortLinkModal();
  } catch (errorRequest) {
    error.textContent = sprintf(copy.shortlinks.errors.save, (errorRequest as Error).message);
    error.removeAttribute('hidden');
  } finally {
    submit.disabled = false;
    submit.removeAttribute('aria-busy');
  }
}

function bindInteractions(): void {
  const bestTimeBtn = document.getElementById('fp-besttime-trigger');
  bestTimeBtn?.addEventListener('click', () => {
    void loadSuggestions();
  });

  const densityToolbar = document.getElementById('fp-calendar-toolbar');
  densityToolbar?.addEventListener('click', (event) => {
    const target = (event.target as HTMLElement).closest<HTMLButtonElement>('[data-calendar-density]');
    if (!target) {
      return;
    }

    event.preventDefault();
    const mode = target.dataset.calendarDensity === 'compact' ? 'compact' : 'comfort';
    setCalendarDensity(mode);
  });

  const calendarContainer = document.getElementById('fp-calendar');
  calendarContainer?.addEventListener('click', (event) => {
    const target = event.target as HTMLElement;
    const slotButton = target.closest<HTMLButtonElement>('.fp-calendar__slot-action');
    if (slotButton) {
      event.preventDefault();
      void handleSlotSuggestion(slotButton, slotButton.dataset.date ?? '');
      return;
    }

    const importButton = target.closest<HTMLButtonElement>('[data-action="calendar-import"]');
    if (importButton) {
      event.preventDefault();
      void importCalendarFromTrello(importButton);
    }
  });

  const kanban = document.querySelector('.fp-kanban');
  kanban?.addEventListener('click', (event) => {
    const target = event.target as HTMLElement;
    if (target?.dataset?.action === 'besttime') {
      event.preventDefault();
      document.getElementById('fp-besttime-section')?.scrollIntoView({ behavior: 'smooth' });
      void loadSuggestions();
    }
  });

  document.getElementById('fp-approvals-approve')?.addEventListener('click', (event) => {
    event.preventDefault();
    void handleApprovalAction('approved');
  });

  document.getElementById('fp-approvals-request')?.addEventListener('click', (event) => {
    event.preventDefault();
    void handleApprovalAction('changes_requested');
  });

  document.getElementById('fp-refresh-comments')?.addEventListener('click', () => {
    void loadComments();
  });

  const form = document.getElementById('fp-comments-form') as HTMLFormElement | null;
  form?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const textarea = form.querySelector('textarea');
    if (!textarea) {
      return;
    }

    const body = textarea.value.trim();
    if (!body) {
      announceCommentUpdate(__('Fill the comment before sending.', TEXT_DOMAIN));
      return;
    }

    try {
      await fetchJSON(`${config.restBase}/plans/1/comments`, {
        method: 'POST',
        body: JSON.stringify({ body }),
      });
      textarea.value = '';
      hideMentionSuggestions();
      announceCommentUpdate(__('Comment sent successfully.', TEXT_DOMAIN));
      await loadComments();
    } catch (error) {
      const list = document.getElementById('fp-comments-list');
      if (list) {
        list.innerHTML = `<p class="fp-comments__error">${escapeHtml(
          sprintf(__('Error while sending (%s).', TEXT_DOMAIN), (error as Error).message),
        )}</p>`;
      }
      announceCommentUpdate(__('Unable to send the comment.', TEXT_DOMAIN));
    }
  });

  if (form) {
    const textarea = form.querySelector('textarea');
    const mentionsList = document.getElementById('fp-mentions-list');
    if (textarea instanceof HTMLTextAreaElement && mentionsList instanceof HTMLUListElement) {
      initMentionAutocomplete(textarea, mentionsList);
    }
  }

  const shortLinkContainer = document.getElementById('fp-shortlink');
  const createButton = document.getElementById('fp-shortlink-create');
  if (createButton instanceof HTMLButtonElement) {
    createButton.addEventListener('click', (event) => {
      event.preventDefault();
      openShortLinkModal('create');
    });
  }

  if (shortLinkContainer instanceof HTMLElement) {
    shortLinkContainer.addEventListener('click', (event) => {
      const target = event.target as HTMLElement;
      const toggle = target.closest<HTMLButtonElement>('[data-shortlink-menu]');
      if (toggle) {
        event.preventDefault();
        toggleShortLinkMenu(toggle);
        return;
      }

      const actionButton = target.closest<HTMLButtonElement>('[data-shortlink-action]');
      if (actionButton) {
        event.preventDefault();
        closeShortLinkMenu();
        void handleShortLinkAction(actionButton);
      }
    });
  }

  document.addEventListener('click', (event) => {
    const target = event.target as HTMLElement;
    if (activeShortLinkMenu && !target.closest('.fp-shortlink__menu')) {
      closeShortLinkMenu();
    }
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && activeShortLinkMenu) {
      const toggle = activeShortLinkMenu;
      closeShortLinkMenu();
      toggle.focus();
    }
  });

  const shortLinkModal = getShortLinkModalElements();
  if (shortLinkModal) {
    const { form, slugInput, targetInput, cancel, close, overlay } = shortLinkModal;
    form.addEventListener('submit', (event) => {
      void handleShortLinkModalSubmit(event);
    });
    slugInput.addEventListener('input', updateShortLinkModalPreview);
    targetInput.addEventListener('input', updateShortLinkModalPreview);
    cancel.addEventListener('click', (event) => {
      event.preventDefault();
      closeShortLinkModal();
    });
    close.addEventListener('click', (event) => {
      event.preventDefault();
      closeShortLinkModal();
    });
    overlay.addEventListener('click', (event) => {
      event.preventDefault();
      closeShortLinkModal();
    });
  }

  syncCalendarDensityButtons();
}

function renderApp(container: HTMLElement, status: { version?: string }): void {
  container.classList.remove('is-loading');
  container.classList.add('is-ready');
  container.innerHTML = `
    <main class="fp-publisher-shell">
      <header class="fp-publisher-shell__header">
        <div>
          <h1 class="fp-publisher-shell__title">FP Digital Publisher</h1>
          <p class="fp-publisher-shell__subtitle">${escapeHtml(
            __('Planning workflow & time suggestions', TEXT_DOMAIN),
          )}</p>
        </div>
        <span class="fp-publisher-shell__version">v${status.version ?? config.version}</span>
      </header>

      <section class="fp-publisher-shell__grid">
        <article class="fp-widget">
          <header class="fp-widget__header">
            <div class="fp-widget__heading">
              <h2>${escapeHtml(__('Editorial calendar', TEXT_DOMAIN))}</h2>
              <span>${monthKey}</span>
            </div>
            <div
              class="fp-calendar__toolbar"
              id="fp-calendar-toolbar"
              role="group"
              aria-label="${escapeHtml(__('Calendar density', TEXT_DOMAIN))}"
            >
              <button
                type="button"
                class="fp-calendar__density-button is-active"
                data-calendar-density="comfort"
                aria-pressed="true"
                aria-controls="fp-calendar"
              >${escapeHtml(__('Comfort', TEXT_DOMAIN))}</button>
              <button
                type="button"
                class="fp-calendar__density-button"
                data-calendar-density="compact"
                aria-pressed="false"
                aria-controls="fp-calendar"
              >${escapeHtml(__('Compact', TEXT_DOMAIN))}</button>
            </div>
          </header>
          <div id="fp-calendar"></div>
        </article>

        <article class="fp-widget fp-kanban" aria-live="polite">
          <header class="fp-widget__header">
            <h2>${escapeHtml(__('Scheduling status', TEXT_DOMAIN))}</h2>
            <span>${escapeHtml(__('Drag & drop (demo)', TEXT_DOMAIN))}</span>
          </header>
          <div id="fp-kanban"></div>
        </article>

        <article class="fp-widget" id="fp-besttime-section">
          <header class="fp-widget__header">
            <h2>${escapeHtml(__('Best time to publish', TEXT_DOMAIN))}</h2>
            <button type="button" class="button" id="fp-besttime-trigger">${escapeHtml(
              __('Suggest time', TEXT_DOMAIN),
            )}</button>
          </header>
          <div id="fp-besttime-results" class="fp-besttime"></div>
        </article>

        <article class="fp-widget fp-composer" id="fp-composer"></article>

        <article class="fp-widget">
          <div id="fp-comments"></div>
        </article>

        <article class="fp-widget" id="fp-alerts"></article>

        <article class="fp-widget" id="fp-logs"></article>

        <article class="fp-widget" id="fp-shortlink">
          <header class="fp-widget__header">
            <div class="fp-widget__heading">
              <h2 id="fp-shortlink-title">${escapeHtml(copy.shortlinks.section.title)}</h2>
              <span>${escapeHtml(copy.shortlinks.section.subtitle)}</span>
            </div>
            <button type="button" class="button button-primary" id="fp-shortlink-create">${escapeHtml(copy.shortlinks.section.createButton)}</button>
          </header>
          <div class="fp-shortlink__body">
            <p id="fp-shortlink-feedback" class="fp-shortlink__feedback" aria-live="polite" hidden></p>
            <div
              class="fp-shortlink__table"
              id="fp-shortlink-table"
              role="region"
              aria-labelledby="fp-shortlink-title"
              aria-live="polite"
            >
              <table>
                <thead>
                  <tr>
                    <th scope="col">${escapeHtml(copy.shortlinks.table.slug)}</th>
                    <th scope="col">${escapeHtml(copy.shortlinks.table.target)}</th>
                    <th scope="col">${escapeHtml(copy.shortlinks.table.clicks)}</th>
                    <th scope="col">${escapeHtml(copy.shortlinks.table.lastClick)}</th>
                    <th scope="col" aria-label="${escapeHtml(copy.shortlinks.table.actions)}">⋯</th>
                  </tr>
                </thead>
                <tbody id="fp-shortlink-rows"></tbody>
              </table>
              <div id="fp-shortlink-skeleton" class="fp-shortlink__skeleton" aria-hidden="true" hidden>
                <div class="fp-shortlink__skeleton-row"></div>
                <div class="fp-shortlink__skeleton-row"></div>
                <div class="fp-shortlink__skeleton-row"></div>
              </div>
            </div>
            <p id="fp-shortlink-empty" class="fp-shortlink__empty" hidden>
              ${escapeHtml(copy.shortlinks.empty)}
            </p>
          </div>
          <div
            class="fp-modal"
            id="fp-shortlink-modal"
            role="dialog"
            aria-modal="true"
            aria-labelledby="fp-shortlink-modal-title"
            hidden
          >
            <div class="fp-modal__backdrop" data-shortlink-modal-overlay></div>
            <div class="fp-modal__dialog" role="document">
              <header class="fp-modal__header">
                <h2 id="fp-shortlink-modal-title">${escapeHtml(copy.shortlinks.modal.createTitle)}</h2>
                <button
                  type="button"
                  class="fp-modal__close"
                  data-shortlink-modal-close
                  aria-label="${escapeHtml(copy.common.close)}"
                >×</button>
              </header>
              <form id="fp-shortlink-modal-form" class="fp-shortlink__form" novalidate>
                <label class="fp-shortlink__field">
                  <span>${escapeHtml(copy.shortlinks.modal.slugLabel)}</span>
                  <input
                    type="text"
                    id="fp-shortlink-input-slug"
                    name="slug"
                    autocomplete="off"
                    required
                    placeholder="${escapeHtml(copy.shortlinks.modal.slugPlaceholder)}"
                  />
                </label>
                <label class="fp-shortlink__field">
                  <span>${escapeHtml(copy.shortlinks.modal.targetLabel)}</span>
                  <input
                    type="url"
                    id="fp-shortlink-input-target"
                    name="target_url"
                    required
                    placeholder="${escapeHtml(copy.shortlinks.modal.targetPlaceholder)}"
                  />
                </label>
                <div id="fp-shortlink-modal-preview" class="fp-shortlink__preview" aria-live="polite">
                  <p>${escapeHtml(copy.shortlinks.modal.previewDefault)}</p>
                </div>
                <p id="fp-shortlink-modal-error" class="fp-shortlink__error" role="alert" hidden></p>
                <footer class="fp-modal__footer">
                  <button type="button" class="button" id="fp-shortlink-modal-cancel">${escapeHtml(copy.shortlinks.modal.cancel)}</button>
                  <button type="submit" class="button button-primary" id="fp-shortlink-modal-submit">${escapeHtml(copy.shortlinks.modal.create)}</button>
                </footer>
              </form>
            </div>
          </div>
        </article>
      </section>
    </main>
  `;

  const calendar = document.getElementById('fp-calendar');
  if (calendar) {
    void renderCalendar(calendar);
  }

  const kanbanContainer = document.getElementById('fp-kanban');
  if (kanbanContainer) {
    renderKanban(kanbanContainer);
    hydrateKanban();
  }

  const composerContainer = document.getElementById('fp-composer');
  if (composerContainer) {
    renderComposer(composerContainer);
    initComposer();
  }

  const commentsContainer = document.getElementById('fp-comments');
  if (commentsContainer) {
    renderComments(commentsContainer);
    void loadComments();
    void loadApprovalsTimeline();
  }

  const alertsContainer = document.getElementById('fp-alerts');
  if (alertsContainer) {
    renderAlertsWidget(alertsContainer);
  }

  const logsContainer = document.getElementById('fp-logs');
  if (logsContainer) {
    renderLogsWidget(logsContainer);
  }

  void loadShortLinks();
  bindInteractions();
}

async function boot(): Promise<void> {
  if (!mount) {
    return;
  }

  mount.classList.add('fp-publisher-admin__mount', 'is-loading');
  mount.innerHTML = `
    <main class="fp-publisher-shell">
      <header class="fp-publisher-shell__header">
        <h1 class="fp-publisher-shell__title">FP Digital Publisher</h1>
        <span class="fp-publisher-shell__version">v${config.version}</span>
      </header>
      <section class="fp-publisher-shell__content">
        <p class="fp-publisher-shell__message">${escapeHtml(
          __('Loading application status…', TEXT_DOMAIN),
        )}</p>
      </section>
    </main>
  `;

  if (!config.restBase || !config.nonce) {
    return;
  }

  try {
    const status = await fetchJSON<{ version?: string }>(`${config.restBase}/status`);
    renderApp(mount, status);
  } catch (error) {
    mount.classList.remove('is-loading');
    mount.classList.add('has-error');
    mount.innerHTML = `
      <main class="fp-publisher-shell">
        <header class="fp-publisher-shell__header">
          <h1 class="fp-publisher-shell__title">FP Digital Publisher</h1>
        </header>
        <section class="fp-publisher-shell__content">
          <p class="fp-publisher-shell__message">${escapeHtml(
            sprintf(__('Error while fetching the status: %s', TEXT_DOMAIN), (error as Error).message),
          )}</p>
        </section>
      </main>
    `;
  }
}

void boot();
