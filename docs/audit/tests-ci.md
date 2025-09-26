# Phase 8 – Tests & Continuous Integration

## PHPUnit test suite
- Added a PHPUnit configuration that boots the existing lightweight WordPress stubs and exposes the plugin classes for unit testing.
- Wrapped the legacy script-based tests inside a PHPUnit data provider so failures surface through the standard runner while maintaining backwards compatibility.
- Authored new smoke tests for `TTS_Plugin_Bootstrap` to verify hook registration, container reuse, and runtime logger toggles under the stubbed WordPress environment.

## Continuous integration
- Replaced the bespoke Bash runner with a phpdbg-aware wrapper for PHPUnit so contributors can execute the suite locally with a single command.
- Introduced a GitHub Actions matrix covering PHP 8.0, 8.1, and 8.2 paired with WordPress 6.4 and 6.5 to ensure automated checks against representative environments.
- Persisted coverage artefacts to `docs/coverage` from the CI job to aid regressions analysis.

## Follow-up work
- The script-based tests should be progressively rewritten as first-class PHPUnit cases to benefit from richer assertions and inline coverage reporting.
- Once the outstanding PHPCS and PHPStan violations are resolved, extend the CI workflow with linting gates.
