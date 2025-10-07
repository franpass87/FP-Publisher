# QA checklist

Use this checklist to verify new features before shipping.

## Local environment

* Install WordPress using the integration test suite or a disposable site.
* Run `composer test` and `composer test:integration` to catch regressions early.
* Ensure the `WP_TESTS_DIR` constant is set when running tests from an IDE.

## Smoke tests (Docker)

Use the provided scripts for a quick end-to-end verification (REST + WP-CLI):

PowerShell (Windows):

```
./tools/smoke-tests.ps1 -ProjectDir fp-digital-publisher -ComposeFile docker-compose.yml
```

Bash/macOS/Linux:

```
bash tools/smoke-tests.sh
```

The scripts will:
- start the WordPress stack via Docker Compose
- install Composer dependencies inside the container
- activate the plugin
- check `GET /wp-json/fp-publisher/v1/health`
- run `wp fp-publisher diagnostics --component=queue`
- enqueue a test job and run the queue once

## Functional testing

* Validate connector flows for Meta, TikTok, YouTube, Google Business, and WordPress.
* Schedule posts across multiple channels and confirm status transitions on the calendar.
* Force failures via the queue simulator and confirm the transient error classifier retries correctly.

## Performance

* Seed at least 500 jobs and ensure the queue queries remain under 50 ms with indexes.
* Run the housekeeping cron to verify archiving and asset purge execution times.
* Measure SPA bundle size after changes; keep it under the size budget defined in `package.json`.

## Accessibility

* Run automated checks (Lighthouse, axe) on the admin SPA.
* Confirm keyboard navigation through the calendar and modal dialogs works as expected.

## Release readiness

* Update the changelog and playbook state before opening the pull request.
* Execute the GitHub Actions workflow or dry-run the release script locally when touching build tooling.
