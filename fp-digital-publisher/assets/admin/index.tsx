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

type ShortLink = {
  slug: string;
  target_url: string;
  clicks: number;
  last_click_at?: string | null;
};

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

function formatDate(date: Date): string {
  return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}

function renderCalendar(container: HTMLElement): void {
  const current = new Date(now.getFullYear(), now.getMonth(), 1);
  const daysInMonth = new Date(now.getFullYear(), now.getMonth() + 1, 0).getDate();
  let html = '<table class="fp-publisher-calendar"><thead><tr>';
  const weekdays = ['Lun', 'Mar', 'Mer', 'Gio', 'Ven', 'Sab', 'Dom'];
  html += weekdays.map((day) => `<th>${day}</th>`).join('');
  html += '</tr></thead><tbody>';

  let day = 1;
  const startOffset = (current.getDay() + 6) % 7; // convert Sunday=0 to Monday=0
  for (let row = 0; row < 6 && day <= daysInMonth; row += 1) {
    html += '<tr>';
    for (let col = 0; col < 7; col += 1) {
      const cellIndex = row * 7 + col;
      if (cellIndex < startOffset || day > daysInMonth) {
        html += '<td class="is-empty"></td>';
      } else {
        const iso = formatDate(new Date(now.getFullYear(), now.getMonth(), day));
        html += `<td data-date="${iso}"><span class="fp-calendar-day">${day}</span></td>`;
        day += 1;
      }
    }
    html += '</tr>';
  }
  html += '</tbody></table>';
  container.innerHTML = html;
}

function renderKanban(container: HTMLElement): void {
  const columns = ['draft', 'ready', 'approved', 'scheduled', 'published', 'failed'];
  const columnTitles: Record<string, string> = {
    draft: 'Bozze',
    ready: 'Pronti',
    approved: 'Approvati',
    scheduled: 'Pianificati',
    published: 'Pubblicati',
    failed: 'Falliti',
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
      <button type="button" class="button button-small" data-action="besttime">Suggerisci orario</button>
    </article>
  `;
}

function renderComments(container: HTMLElement): void {
  container.innerHTML = `
    <header class="fp-comments__header">
      <h3>Commenti piano</h3>
      <button type="button" class="button" id="fp-refresh-comments">Aggiorna</button>
    </header>
    <div id="fp-comments-list" class="fp-comments__list" aria-live="polite"></div>
    <form id="fp-comments-form" class="fp-comments__form">
      <label>
        <span class="screen-reader-text">Nuovo commento</span>
        <textarea name="body" rows="3" required placeholder="Scrivi un commento… (usa @utente per menzionare)"></textarea>
      </label>
      <button type="submit" class="button button-primary">Invia</button>
    </form>
  `;
}

function renderSuggestions(container: HTMLElement, suggestions: Suggestion[]): void {
  if (suggestions.length === 0) {
    container.innerHTML = '<p class="fp-besttime__empty">Nessun suggerimento disponibile per il periodo selezionato.</p>';
    return;
  }

  container.innerHTML = suggestions
    .slice(0, 6)
    .map(
      (item) => `
        <article class="fp-besttime__item">
          <h4>${new Date(item.datetime).toLocaleString()}</h4>
          <p>${item.reason}</p>
          <span class="fp-besttime__score">Score ${item.score}</span>
        </article>
      `,
    )
    .join('');
}

async function fetchJSON<T>(url: string, options: RequestInit = {}): Promise<T> {
  const response = await fetch(url, {
    credentials: 'same-origin',
    headers: {
      'Content-Type': 'application/json',
      'X-WP-Nonce': config.nonce,
    },
    ...options,
  });

  if (!response.ok) {
    throw new Error(`HTTP ${response.status}`);
  }

  return response.json() as Promise<T>;
}

async function loadSuggestions(): Promise<void> {
  const container = document.getElementById('fp-besttime-results');
  if (!container) {
    return;
  }

  container.innerHTML = '<p class="fp-besttime__loading">Calcolo suggerimenti…</p>';
  try {
    const data = await fetchJSON<{ suggestions: Suggestion[] }>(
      `${config.restBase}/besttime?brand=${encodeURIComponent(config.brand ?? 'brand-demo')}&channel=${activeChannel}&month=${monthKey}`,
    );
    renderSuggestions(container, data.suggestions);
  } catch (error) {
    container.innerHTML = `<p class="fp-besttime__error">Impossibile recuperare i suggerimenti (${(error as Error).message}).</p>`;
  }
}

async function loadComments(): Promise<void> {
  const list = document.getElementById('fp-comments-list');
  if (!list) {
    return;
  }

  list.innerHTML = '<p class="fp-comments__loading">Caricamento commenti…</p>';
  try {
    const data = await fetchJSON<{ items: CommentItem[] }>(`${config.restBase}/plans/1/comments`);
    if (!data.items.length) {
      list.innerHTML = '<p class="fp-comments__empty">Nessun commento disponibile.</p>';
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
            <p>${item.body}</p>
          </article>
        `,
      )
      .join('');
  } catch (error) {
    list.innerHTML = `<p class="fp-comments__error">Impossibile caricare i commenti (${(error as Error).message}).</p>`;
  }
}

async function loadShortLinks(): Promise<void> {
  const container = document.getElementById('fp-shortlink-result');
  if (!container) {
    return;
  }

  container.innerHTML = '<p class="fp-shortlink__loading">Caricamento short link…</p>';
  try {
    const data = await fetchJSON<{ items: ShortLink[] }>(`${config.restBase}/links`);
    if (!data.items.length) {
      container.innerHTML = '<p class="fp-shortlink__empty">Nessun short link configurato.</p>';
      return;
    }

    const latest = data.items[0];
    const goUrl = `${window.location.origin.replace(/\/$/, '')}/go/${latest.slug}`;
    container.innerHTML = `
      <p class="fp-shortlink__preview">
        Ultimo link: <code>${goUrl}</code><br />
        <span>${latest.target_url}</span>
      </p>
    `;
  } catch (error) {
    container.innerHTML = `<p class="fp-shortlink__error">Impossibile caricare i link (${(error as Error).message}).</p>`;
  }
}

function bindInteractions(): void {
  const bestTimeBtn = document.getElementById('fp-besttime-trigger');
  bestTimeBtn?.addEventListener('click', () => {
    void loadSuggestions();
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
      return;
    }

    try {
      await fetchJSON(`${config.restBase}/plans/1/comments`, {
        method: 'POST',
        body: JSON.stringify({ body }),
      });
      textarea.value = '';
      await loadComments();
    } catch (error) {
      const list = document.getElementById('fp-comments-list');
      if (list) {
        list.innerHTML = `<p class="fp-comments__error">Errore durante l\'invio (${(error as Error).message}).</p>`;
      }
    }
  });

  const shortLinkForm = document.getElementById('fp-shortlink-form') as HTMLFormElement | null;
  shortLinkForm?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const result = document.getElementById('fp-shortlink-result');
    if (!result) {
      return;
    }

    const formData = new FormData(shortLinkForm);
    const slug = String(formData.get('slug') ?? '').trim();
    const target = String(formData.get('target_url') ?? '').trim();

    if (!slug || !target) {
      result.innerHTML = '<p class="fp-shortlink__error">Compilare slug e URL di destinazione.</p>';
      return;
    }

    result.innerHTML = '<p class="fp-shortlink__loading">Salvataggio link…</p>';
    try {
      const data = await fetchJSON<{ link: ShortLink }>(`${config.restBase}/links`, {
        method: 'POST',
        body: JSON.stringify({ slug, target_url: target }),
      });

      const goUrl = `${window.location.origin.replace(/\/$/, '')}/go/${data.link.slug}`;
      result.innerHTML = `
        <p class="fp-shortlink__success">
          Link pronto: <code>${goUrl}</code><br />
          <span>${data.link.target_url}</span>
        </p>
      `;
      shortLinkForm.reset();
    } catch (error) {
      result.innerHTML = `<p class="fp-shortlink__error">Errore durante il salvataggio (${(error as Error).message}).</p>`;
    }
  });
}

function renderApp(container: HTMLElement, status: { version?: string }): void {
  container.classList.remove('is-loading');
  container.classList.add('is-ready');
  container.innerHTML = `
    <main class="fp-publisher-shell">
      <header class="fp-publisher-shell__header">
        <div>
          <h1 class="fp-publisher-shell__title">FP Digital Publisher</h1>
          <p class="fp-publisher-shell__subtitle">Planning workflow &amp; suggerimenti orari</p>
        </div>
        <span class="fp-publisher-shell__version">v${status.version ?? config.version}</span>
      </header>

      <section class="fp-publisher-shell__grid">
        <article class="fp-widget">
          <header class="fp-widget__header">
            <h2>Calendario editoriale</h2>
            <span>${monthKey}</span>
          </header>
          <div id="fp-calendar"></div>
        </article>

        <article class="fp-widget fp-kanban" aria-live="polite">
          <header class="fp-widget__header">
            <h2>Stato pianificazioni</h2>
            <span>Drag &amp; drop (demo)</span>
          </header>
          <div id="fp-kanban"></div>
        </article>

        <article class="fp-widget" id="fp-besttime-section">
          <header class="fp-widget__header">
            <h2>Miglior orario di pubblicazione</h2>
            <button type="button" class="button" id="fp-besttime-trigger">Suggerisci orario</button>
          </header>
          <div id="fp-besttime-results" class="fp-besttime"></div>
        </article>

        <article class="fp-widget">
          <div id="fp-comments"></div>
        </article>

        <article class="fp-widget" id="fp-shortlink">
          <header class="fp-widget__header">
            <h2>Short link rapido</h2>
          </header>
          <form id="fp-shortlink-form" class="fp-shortlink__form">
            <label>
              <span>Slug</span>
              <input type="text" name="slug" required placeholder="promo-social" />
            </label>
            <label>
              <span>URL di destinazione</span>
              <input type="url" name="target_url" required placeholder="https://esempio.com/promo" />
            </label>
            <button type="submit" class="button button-primary">Salva short link</button>
          </form>
          <div id="fp-shortlink-result" class="fp-shortlink__result" aria-live="polite"></div>
        </article>
      </section>
    </main>
  `;

  const calendar = document.getElementById('fp-calendar');
  if (calendar) {
    renderCalendar(calendar);
  }

  const kanbanContainer = document.getElementById('fp-kanban');
  if (kanbanContainer) {
    renderKanban(kanbanContainer);
    hydrateKanban();
  }

  const commentsContainer = document.getElementById('fp-comments');
  if (commentsContainer) {
    renderComments(commentsContainer);
    void loadComments();
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
        <p class="fp-publisher-shell__message">Caricamento stato applicazione…</p>
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
          <p class="fp-publisher-shell__message">Errore nel recupero dello stato: ${(error as Error).message}</p>
        </section>
      </main>
    `;
  }
}

void boot();
