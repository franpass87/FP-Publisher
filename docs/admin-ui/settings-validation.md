# Settings Validation & Messaging

This phase enforces consistent capability checks, sanitization, and operator feedback across the global settings form.

## Sanitization rules

- **Capability & nonce:** the sanitize callback exits unless the current user can `manage_options`, preventing unprivileged updates.
- **Column mapping JSON:** values must decode to a JSON object; invalid payloads are rejected with an error notice and the stored value cleared.
- **Log retention days:** coerced to a minimum of `1`; invalid entries fall back to `30` days and surface an error.
- **URL shortener selection:** only `none`, `wp`, or `bitly` persist. Unknown values revert to `none` with feedback.
- **Notification emails:** addresses are trimmed, individually sanitized, and rejoined. When no valid email remains the list is emptied and a notice is displayed.
- **UTM parameters:** both channel and parameter keys are normalized with `sanitize_key` and values sanitized as text.
- **Template / text inputs:** every free-form string is processed via `sanitize_text_field`; URLs pass through `esc_url_raw`.

## Markup & accessibility updates

- Every field printed by the Settings API now exposes a matching `id` so the generated table labels remain accessible.
- Inline descriptions receive `aria-describedby` bindings, and number inputs use semantic types for offsets, coordinates, and retention days.
- Both the legacy `TTS_Settings` page and the refit admin screen output `settings_errors( 'tts_settings' )`, allowing WordPress notices to surface alongside the form.

## Manual regression checks

1. Save valid settings and confirm the green “Settings saved” notice and persistence of values.
2. Submit malformed column mapping JSON and verify the new error notice and the stored value clearing.
3. Enter `0` for log retention; confirm the error and fallback to `30` days.
4. Provide an unsupported URL shortener slug (e.g. `custom`) and verify it resets to “None” with a notice.
5. Enter an invalid email address and confirm the list is emptied and the warning is shown.
6. Switch between usage profiles and ensure radio inputs remain labeled and persist after save.
