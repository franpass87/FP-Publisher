# Fix Changelog

| ID | File | Line | Severity | Fix summary | Commit |
| --- | --- | --- | --- | --- | --- |
| ISSUE-001 | fp-digital-publisher/src/Support/Dates.php | 20 | High | Align default timezone helpers and option sanitizers with the site timezone, falling back to UTC when unavailable. | fix(functional): align timezone defaults with site settings (ISSUE-001) |
| ISSUE-004 | fp-digital-publisher/src/Services/WordPress/Publisher.php | 61 | High | Switch to the target blog before scheduling posts so multisite jobs honor each site's timezone. | fix(multisite): switch to target blog before scheduling posts (ISSUE-004) |
| ISSUE-002 | fp-digital-publisher/src/Api/Routes.php | 862 | Medium | Allow Authorization-based REST integrations to bypass nonce checks while keeping CSRF protection for cookie sessions. | fix(api): allow application password auth without nonce (ISSUE-002) |
| ISSUE-003 | fp-digital-publisher/src/Api/Routes.php | 425 | Medium | Paginate plan listings directly in SQL and return totals without loading the entire table. | fix(performance): paginate plan listing query (ISSUE-003) |
| ISSUE-005 | fp-digital-publisher/src/Services/WordPress/Publisher.php | 440 | Low | Localize the fallback WordPress error message using the plugin text domain. | fix(i18n): translate fallback WordPress error message (ISSUE-005) |

## Remediation Summary

All five audited issues have been resolved and the remediation phase is complete as of 2025-10-01.
