# Admin design tokens & foundation styles

The design-tokens phase establishes a common visual language for every FP Publisher admin screen.
Values intentionally align with WordPress admin defaults so the plugin continues to respect
custom color schemes, typography, and accessibility expectations.

## Token categories

| Domain | Token prefix | Example variables | Notes |
| --- | --- | --- | --- |
| Typography | `--fp-admin-font-*`, `--fp-admin-line-height-*` | `--fp-admin-font-size-md`, `--fp-admin-line-height-base` | Font stacks extend the WordPress admin font family and expose a secondary family (`Inter`) for headings or highlights. |
| Spacing | `--fp-admin-space-*` | `--fp-admin-space-0…9` | 4px baseline scale used for layout rhythm, grid gaps, and component padding. |
| Color | `--fp-admin-color-*`, `--fp-admin-surface*`, `--fp-admin-border*` | `--fp-admin-color-primary`, `--fp-admin-surface-subtle` | Primary color is tied to the active WP admin theme with fallbacks. Soft variants provide accessible backgrounds for notices and badges. |
| Radius & elevation | `--fp-admin-radius-*`, `--fp-admin-shadow-*` | `--fp-admin-radius-lg`, `--fp-admin-shadow-sm` | Radii follow 2/4/8/12px tiers and map to gentle shadows for elevated surfaces. |
| Motion & controls | `--fp-admin-transition-*`, `--fp-admin-control-height-*`, `--fp-admin-focus-ring` | `--fp-admin-transition-base`, `--fp-admin-control-height-md` | Focus ring mirrors WP focus behavior but with a 1.5px inner + 4px outer ring for clarity. |
| Legacy bridge | `--tts-*` | `--tts-primary`, `--tts-border-radius` | Aliases point to the new tokens so existing CSS continues to work while we migrate modules. |

Tokens live in [`assets/src/admin/tokens.css`](../../wp-content/plugins/trello-social-auto-publisher/assets/src/admin/tokens.css). Changes to the token vocabulary must
be made there so that the build system, utilities, and future component library stay in sync.

## Foundation stylesheet

[`assets/src/admin/base.css`](../../wp-content/plugins/trello-social-auto-publisher/assets/src/admin/base.css) layers the core layout primitives:

- Body/wrap padding and background that respect WP spacing on large and medium breakpoints.
- Shared wrappers (`.fp-admin-screen`, `.fp-admin-card`, `.fp-admin-grid`) with responsive behaviour.
- Form rows, help text, and badge treatments that align typography and contrast.
- Menu polish for the top-level FP Publisher entry using the same scale as WP core menus.
- Focus-visible overrides that reuse the tokenised focus ring.

The compiled entry [`admin/css/tts-foundation.css`](../../wp-content/plugins/trello-social-auto-publisher/admin/css/tts-foundation.css)
imports tokens + base and is exposed through the build manifest as `tts-foundation`.

## Loading & dependencies

- `TTS_Admin::enqueue_core_assets()` now enqueues `tts-foundation` before `tts-core`. Any page
  that depends on `tts-core` automatically gains the shared layout primitives and token definitions.
- To use only the primitives (e.g. future lightweight screens), enqueue the `tts-foundation`
  handle directly via `TTS_Asset_Manager::enqueue_style( 'tts-foundation', 'admin/css/tts-foundation.css' )`.
- Custom bundles should list `tts-foundation` (or `tts-core`, if they still rely on legacy classes)
  as a dependency when calling `TTS_Asset_Manager::enqueue_style`.

## Build & contribution workflow

1. Edit tokens or base styles under `assets/src/admin/`.
2. Run `npm run build` inside the plugin directory to regenerate hashed assets and update
   `admin/dist/manifest.json`.
3. Verify that `tts-foundation` (and any dependent bundles) load correctly in the target screen.
4. Document new tokens, utilities, or helper classes in this file to keep the design system discoverable.

Future phases (components, screen refits) should build atop these tokens rather than redefining
colors or spacing. That keeps the redesign cohesive and simplifies future theming or white-label
requirements.
