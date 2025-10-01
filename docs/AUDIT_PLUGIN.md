# Plugin Audit Report — FP Digital Publisher — 2024-10-08

## Summary
- Files scanned: 144/144
- Audit status: Complete (phase `audit_complete`)
- Issues found: 5 (Critical: 0 | High: 2 | Medium: 2 | Low: 1)
- Key risks:
  - Queue scheduling, blackout windows, and templates use a hardcoded `Europe/Rome` timezone, desynchronizing publications on non-Italian sites.
  - Multisite WordPress publishing calculates post dates before switching to the destination blog, so scheduled posts inherit the runner site's timezone.
  - REST endpoints reject authenticated requests that lack a WordPress REST nonce, blocking application passwords and server-to-server automation.
  - Plan listing endpoint performs `SELECT *` with in-PHP filtering, risking timeouts on large datasets.
- Recommended priorities: 1) ISSUE-001 2) ISSUE-004 3) ISSUE-002 4) ISSUE-003

## Manifest mismatch
- Previous manifest hash: `e5f3004821e9c6c7aead466f6d705ce7d32a41278d88929a0f3793c577da2024`
- Current manifest hash: `74489831ff48080e11bd6698f11fb2a189e5cc6df40c77b9d7e29ec973781798`
- Added files:
  - fp-digital-publisher/composer.lock
- Removed files: _None_

## Issues
### [High] Hardcoded timezone ignores site configuration
- ID: ISSUE-001
- File: fp-digital-publisher/src/Support/Dates.php:17
- Snippet:
  ```php
  public const DEFAULT_TZ = 'Europe/Rome';

  if ($timezone === null) {
      return new DateTimeZone(self::DEFAULT_TZ);
  }
  ```

Diagnosis: All time-related helpers default to `Europe/Rome` instead of honoring the WordPress timezone option. `Options::getDefaults()` persists the same default, so scheduling, blackout windows, alerts, and rendered templates run in Rome time unless the admin manually overrides it in plugin settings.

Impact: Functional/compatibility — non-Italian sites will see queue jobs, best-time suggestions, blackout checks, and email timestamps shifted by the site offset (e.g., 6–9 hours for the Americas). This breaks scheduling accuracy and undermines trust in the automation on WP 6.6+ where site timezone should be authoritative.

Proposed fix (concise):

  ```php
  $siteTz = function_exists('wp_timezone_string') ? wp_timezone_string() : get_option('timezone_string');
  $defaultTz = $siteTz !== '' ? $siteTz : 'UTC';
  ```
  - In `Dates::timezone()` and `Options::getDefaults()`, pull from `wp_timezone()` / `wp_timezone_string()` with a safe fallback (e.g. `UTC`).
  - When sanitizing timezone options, default to the site timezone rather than the hardcoded constant.

Side effects / Regression risk: Low — relies on core APIs; ensure migrations respect previously saved custom values.

Est. effort: M

Tags: #functional #compatibility #scheduling #timezone

### [Medium] REST API enforces nonce even for authenticated integrations
- ID: ISSUE-002
- File: fp-digital-publisher/src/Api/Routes.php:860
- Snippet:
  ```php
  if (! self::verifyNonce($request)) {
      return new WP_Error('fp_publisher_invalid_nonce', ...);
  }
  ```

Diagnosis: `authorize()` returns a 403 unless the request provides a `wp_rest` nonce. WordPress application passwords, Basic Auth proxies, or server-to-server scripts authenticate via HTTP Authorization headers and do not send nonces, so valid credentials are rejected before capability checks run.

Impact: Compatibility — blocks integrations that rely on core REST authentication methods (application passwords, OAuth proxies, CLI). Network admins cannot manage FP Publisher remotely even with proper capabilities, limiting enterprise automation.

Proposed fix (concise):

  ```php
  private static function authorize(...) {
      if (! self::verifyNonce($request) && ! $request->get_header('Authorization')) {
          // only enforce nonce for cookie-based calls
      }
  }
  ```
  - Detect authenticated contexts (`is_user_logged_in()`, `wp_is_application_passwords_available()`, or presence of `Authorization` headers) and skip the nonce requirement there.
  - Keep nonce checks for browser-based requests to retain CSRF coverage.

Side effects / Regression risk: Low — behaviour aligns with WP REST default expectations; regression risk lies in loosening nonce checks, so guard strictly for authenticated sessions.

Est. effort: S

Tags: #compatibility #api #nonce #authentication

### [Medium] Plan listing API loads entire table without pagination
- ID: ISSUE-003
- File: fp-digital-publisher/src/Api/Routes.php:386
- Snippet:
  ```php
  $rows = $wpdb->get_results("SELECT * FROM {$table}", ARRAY_A);
  ... foreach ($rows as $row) { ... filter in PHP ... }
  ```

Diagnosis: `/plans` REST endpoint fetches every row (`SELECT *`) and then applies brand, channel, and month filters in PHP. Large installations will load thousands of plans into memory and iterate them for every request, regardless of filters.

Impact: Performance — on busy sites the admin UI and API can time out or exhaust memory, especially on shared hosting without object caching. REST responses also lack pagination, so clients cannot request smaller result sets.

Proposed fix (concise):

  ```php
  $where = [];
  $params = [];
  // append conditions for brand/channel/month
  $sql = "SELECT * FROM {$table} WHERE " . implode(' AND ', $where)
       . ' ORDER BY scheduled_at DESC LIMIT %d OFFSET %d';
  $rows = $wpdb->get_results($wpdb->prepare($sql, ...));
  ```
  - Build SQL filters (`WHERE brand = %s`, `JSON_EXTRACT`/ `LIKE` for channels or join helper tables) and paginate (`LIMIT/OFFSET`).
  - Expose pagination metadata (`total`, `page`) similar to `Queue::paginate()`.

Side effects / Regression risk: Medium — requires query refactor; ensure JSON filtering logic matches existing PHP behaviour and add coverage for empty datasets.

Est. effort: M

Tags: #performance #wpquery #rest #pagination

### [High] Multisite WordPress publisher uses runner-site timezone
- ID: ISSUE-004
- File: fp-digital-publisher/src/Services/WordPress/Publisher.php:49
- Snippet:
  ```php
  $publishAt = self::resolvePublishAt($payload, $plan);
  self::applySchedule($postData, $publishAt);

  $switched = self::maybeSwitchBlog($payload);
  ```

  ```php
  $timezone = wp_timezone();
  $local = Dates::ensure($publishAt, $timezone);
  ```

Diagnosis: `process()` calls `applySchedule()` before switching to the target blog on multisite installs. `applySchedule()` pulls the active site's timezone via `wp_timezone()`, so jobs destined for a different site inherit the runner blog's offset instead of the destination site's settings.

Impact: Functional/compatibility — multisite queues post to the wrong local time when blogs use different timezone options (common in international networks). Scheduled posts publish hours early/late, violating automation guarantees and WP multisite compatibility requirements.

Repro steps:
1. Configure a multisite where the network main site uses UTC and a child site uses `America/New_York`.
2. Queue a WordPress publishing job targeting the child site at 09:00 local time.
3. Observe that the stored `post_date`/`post_date_gmt` reflect UTC scheduling, not the child site's timezone.

Proposed fix (concise):

  ```php
  $switched = self::maybeSwitchBlog($payload);
  try {
      self::applySchedule($postData, $publishAt);
      // … insert post …
  }
  ```
  - Switch to the target blog (or fetch its timezone via `get_blog_option($blog_id, 'timezone_string')`) before deriving `$post_date` so conversions use the destination site's settings.
  - Ensure preview responses still avoid switching by guarding on `$payload['preview']`.

Side effects / Regression risk: Medium — moving the switch earlier changes when context runs; verify that term resolution and previews still function and that queue processing restores the original blog.

Est. effort: M

Tags: #functional #multisite #timezone #scheduler

### [Low] WordPress error fallback string not translated
- ID: ISSUE-005
- File: fp-digital-publisher/src/Services/WordPress/Publisher.php:440
- Snippet:
  ```php
  return $message !== '' ? $message : 'Errore WordPress sconosciuto.';
  ```

Diagnosis: The fallback error string is hard-coded in Italian and bypasses the plugin text domain, so localized sites display untranslated copy and English locales see Italian text.

Impact: UX/i18n — breaks localization across non-Italian sites and conflicts with WP coding standards for translatable strings.

Proposed fix (concise):

  ```php
  $fallback = __('Unknown WordPress error.', 'fp-publisher');
  return $message !== '' ? $message : $fallback;
  ```
  - Wrap the fallback in `__()` with the plugin text domain and add an English source string to the POT file.

Side effects / Regression risk: Low — text-only change routed through localization infrastructure.

Est. effort: S

Tags: #i18n #ux #admin-notice

## Conflicts & Duplicates
None observed in this batch.

## Deprecated & Compatibility
- Timezone handling defaults to `Europe/Rome`; align defaults with `wp_timezone_string()` to remain locale-agnostic.
- Multisite WordPress publishing derives schedule timestamps before switching to the destination blog (ISSUE-004); move timezone resolution after `switch_to_blog()`.
- REST routes currently incompatible with application passwords because of mandatory nonce checks (ISSUE-002).

## Performance Hotspots
- `Routes::listPlans()` loads entire `fp_pub_plans` table; implement SQL filtering and pagination (ISSUE-003).
- `Services::Links::all()` (`fp-digital-publisher/src/Services/Links.php:79-90`) issues `SELECT *` for every request; consider pagination or `LIMIT` for large link sets.

## i18n & A11y
- No new text-domain or accessibility issues detected in the scanned files; continue to ensure translated strings cover newly added UI copy.

## Test Coverage
- The suite includes unit and integration tests, but no coverage was found for timezone defaults or REST authentication fallbacks. Add regression tests once fixes land (e.g., asserting `Dates::timezone()` honours `wp_timezone()`).

## Next Steps (per fase di FIX)
- Ordine consigliato: ISSUE-001 → ISSUE-004 → ISSUE-002 → ISSUE-003
- Safe-fix batch plan:
  - Lotto 1: ISSUE-001 & ISSUE-004 (timezone defaults and multisite scheduling order)
  - Lotto 2: ISSUE-002 (REST authorization guard)
  - Lotto 3: ISSUE-003 plus `Links::all()` pagination refinements and accompanying tests, plus ISSUE-005 (localize fallback strings)
