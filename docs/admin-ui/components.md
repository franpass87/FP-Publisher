# Admin UI Component Library

Phase [4] introduces a reusable component layer that pairs with the design tokens
and base foundation added in previous steps. All components live in
`assets/src/admin/components.css` and are bundled through the
`tts-components` handle. Templates or JS views should enqueue the
`tts-foundation` and `tts-components` styles (or depend on `tts-core`, which now
brings both along).

## Interaction & accessibility guidelines

- Every interactive element now receives the shared focus ring defined in the
  design tokens (`--fp-admin-focus-ring`) plus an explicit outline. Avoid
  removing these indicators; instead, inherit the defaults so keyboard users can
  identify the current target on any background.
- When creating new composite widgets (tablists, filter groups, cards) wrap the
  control set in a landmark or `role="group"` and point `aria-labelledby` to the
  visible heading. Descriptive leads should use `aria-describedby` with
  generated IDs (`wp_unique_id()`) so assistive tech announces the supporting
  context.
- Animations in component layers automatically disable transforms when users
  prefer reduced motion. Keep additional effects guarded by the same media
  query.

## Page Header

The page header establishes the layout for primary titles, contextual metadata
and high-priority actions.

```html
<header class="fp-admin-page-header" aria-labelledby="audience-overview">
    <div>
        <h1 id="audience-overview" class="fp-admin-page-header__title">
            Audience overview
        </h1>
        <p class="fp-admin-page-header__subtitle">Last synced 2 hours ago</p>
        <p class="fp-admin-page-header__lead">
            Track performance across every connected network and surface export
            actions without leaving the dashboard.
        </p>
    </div>
    <div class="fp-admin-page-header__actions" role="group" aria-label="Primary actions">
        <a class="button button-primary" href="<?php echo esc_url( $export_url ); ?>">
            <?php esc_html_e( 'Export report', 'trello-social-auto-publisher' ); ?>
        </a>
        <button type="button" class="button">
            <?php esc_html_e( 'Refresh data', 'trello-social-auto-publisher' ); ?>
        </button>
    </div>
</header>
```

## Toolbar

Use the toolbar for filter chips, view toggles or other secondary actions that
should remain visible while scrolling. Add the `fp-admin-toolbar--sticky`
modifier when persistent visibility is required.

```html
<div class="fp-admin-toolbar fp-admin-toolbar--sticky" role="region" aria-label="Dashboard filters">
    <div class="fp-admin-toolbar__group">
        <button type="button" class="button" aria-pressed="true">All clients</button>
        <button type="button" class="button" aria-pressed="false">My clients</button>
    </div>
    <div class="fp-admin-toolbar__group fp-admin-toolbar__group--align-end">
        <button type="button" class="button button-secondary">
            <?php esc_html_e( 'Reset filters', 'trello-social-auto-publisher' ); ?>
        </button>
    </div>
</div>
```

## Card / Panel

Cards provide a neutral surface for metrics, summaries or nested forms. They
support header, body and footer regions.

```html
<section class="fp-admin-card" aria-labelledby="schedule-card-title">
    <div class="fp-admin-card__header">
        <h2 id="schedule-card-title" class="fp-admin-card__title">
            <?php esc_html_e( 'Posting schedule', 'trello-social-auto-publisher' ); ?>
        </h2>
        <span class="fp-admin-badge fp-admin-badge--success">
            <?php esc_html_e( 'Healthy', 'trello-social-auto-publisher' ); ?>
        </span>
    </div>
    <p class="fp-admin-help-text">
        <?php esc_html_e( 'Update the cadence across every connected board.', 'trello-social-auto-publisher' ); ?>
    </p>
    <footer class="fp-admin-card__footer">
        <button type="button" class="button button-primary">
            <?php esc_html_e( 'Edit schedule', 'trello-social-auto-publisher' ); ?>
        </button>
    </footer>
</section>
```

Group cards with the grid helpers when multiple panels need alignment.

```html
<div class="fp-admin-grid fp-admin-grid--two">
    <!-- .fp-admin-card items -->
</div>
```

## Form Row

The form row consolidates label, control, help and error messaging in a single
pattern with sensible spacing. Use the inline modifier when labels should remain
beside inputs on wider screens.

```php
<div class="fp-admin-form-row fp-admin-form-row--inline">
    <label class="fp-admin-form-row__label" for="fp-default-board">
        <?php esc_html_e( 'Default board', 'trello-social-auto-publisher' ); ?>
        <span class="fp-admin-form-row__required" aria-hidden="true">*</span>
    </label>
    <div class="fp-admin-form-row__control">
        <select id="fp-default-board" name="fp_default_board">
            <?php foreach ( $boards as $board_id => $label ) : ?>
                <option value="<?php echo esc_attr( $board_id ); ?>">
                    <?php echo esc_html( $label ); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p id="fp-default-board-help" class="fp-admin-form-row__help">
            <?php esc_html_e( 'Choose the board used when new posts are created.', 'trello-social-auto-publisher' ); ?>
        </p>
        <?php if ( ! empty( $errors['fp_default_board'] ) ) : ?>
            <p class="fp-admin-form-row__error" role="alert">
                <?php echo esc_html( $errors['fp_default_board'] ); ?>
            </p>
        <?php endif; ?>
    </div>
</div>
```

## Tab Navigation

Tabs follow the WAI-ARIA Authoring Practices. Set `role="tablist"` on the
container and pair each tab with a labelled panel using `aria-controls`.

```html
<div class="fp-admin-tablist" role="tablist" aria-label="Wizard steps">
    <button type="button" class="fp-admin-tab" role="tab" id="step-1" aria-controls="panel-1" aria-selected="true">
        <?php esc_html_e( 'Connect', 'trello-social-auto-publisher' ); ?>
    </button>
    <button type="button" class="fp-admin-tab" role="tab" id="step-2" aria-controls="panel-2" aria-selected="false">
        <?php esc_html_e( 'Configure', 'trello-social-auto-publisher' ); ?>
    </button>
</div>
<div id="panel-1" role="tabpanel" aria-labelledby="step-1">
    <!-- Tab content -->
</div>
```

## Notices

Use the notice helpers to add hierarchy and align call-to-action buttons. Apply
`fp-admin-notice--info`, `--success`, `--warning` or `--error` for contextual
color.

```html
<div class="notice notice-info fp-admin-notice fp-admin-notice--info" role="status">
    <h3 class="fp-admin-notice__title">
        <?php esc_html_e( 'Heads up!', 'trello-social-auto-publisher' ); ?>
    </h3>
    <p><?php esc_html_e( 'Queue processing is temporarily slower while we sync analytics.', 'trello-social-auto-publisher' ); ?></p>
    <div class="fp-admin-notice__actions">
        <a class="button" href="<?php echo esc_url( $status_url ); ?>">
            <?php esc_html_e( 'View status', 'trello-social-auto-publisher' ); ?>
        </a>
        <button type="button" class="button">
            <?php esc_html_e( 'Remind me later', 'trello-social-auto-publisher' ); ?>
        </button>
    </div>
</div>
```

## Quick Actions

`fp-admin-quick-actions` wraps short task lists or shortcuts, aligning the
buttons with consistent sizing.

```html
<div class="fp-admin-quick-actions" role="group" aria-label="Quick links">
    <a class="button" href="<?php echo esc_url( $wizard_url ); ?>">
        <?php esc_html_e( 'Launch wizard', 'trello-social-auto-publisher' ); ?>
    </a>
    <a class="button" href="<?php echo esc_url( $docs_url ); ?>">
        <?php esc_html_e( 'View docs', 'trello-social-auto-publisher' ); ?>
    </a>
</div>
```

## Implementation notes

- Components rely on the design tokens exposed in `tts-foundation`. Always load
  the foundation layer first.
- Focus states lean on the shared focus ring declared in the tokens so custom
  markup keeps parity with core WordPress controls.
- Prefer semantic HTML (e.g. `<header>`, `<section>`, `<nav>`) and wire ARIA
  attributes only when required for screen reader context.
- When building new screens, compose these primitives instead of introducing new
  bespoke class names; this keeps maintenance predictable across iterations.
