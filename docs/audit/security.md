# Security Hardening – Phase 4

## Summary of Findings
- Backup management endpoints accepted arbitrary filenames, enabling directory traversal and access to unexpected files.
- AJAX handlers for restoring and deleting backups only sanitized filenames, returning ambiguous errors that could be bypassed with crafted payloads.
- Download endpoints exposed raw filesystem paths without validating extension patterns or enforcing canonical directories.

## Remediations Implemented
- Added `normalise_backup_filename()` and `get_backup_path()` helpers to strictly validate filenames against the expected `tts-backup-*.json(.gz)` pattern and ensure resolved paths remain inside the configured backup directory.
- Hardened `ajax_restore_backup()`, `ajax_delete_backup()`, and `ajax_download_backup()` to surface descriptive JSON or die responses when validation fails instead of silently proceeding.
- Updated `restore_backup()`, `delete_backup()`, and `download_backup()` to consume the secure helpers, handle `WP_Error` responses, and maintain capability checks before filesystem access.

## Outstanding Actions
- Consider introducing keyed HMACs in future phases to detect tampering before imports or restores execute.
- Expand automated test coverage around backup filename validation and restore flows once the testing harness is available.
