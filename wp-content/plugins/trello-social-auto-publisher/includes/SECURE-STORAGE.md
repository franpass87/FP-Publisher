# Secure Storage and Token Management

The FP Publisher plugin protects access tokens, application secrets and other
sensitive metadata stored via `update_post_meta()` by transparently encrypting
values at rest. The encryption layer is implemented in
`TTS_Secure_Storage` and is loaded automatically when the plugin boots.

## Encryption overview

* Encryption uses OpenSSL AES-256-GCM with per-value IVs and integrity tags.
* Managed keys can be provided through one of the following mechanisms:
  * Environment variable `TTS_ENCRYPTION_KEY` containing a 32-byte key (raw,
    hex encoded or base64 encoded).
  * The `TTS_ENCRYPTION_KEY` PHP constant for static deployments.
  * Custom providers can hook `tts_secure_storage_managed_key` or
    `tts_secure_storage_resolve_key_by_id` to fetch keys from an external
    vault, HSM or KMS.
* Stored payloads include the key identifier so that rotated keys can be
  resolved on demand.

## Vault integrations

`TTS_Secure_Storage::resolve_managed_secret()` supports declarative secret
references in options and metadata. The following syntaxes are available:

* `vault:aws-kms:<alias-or-arn>` &mdash; resolved through the
  `tts_vault_resolve_aws-kms` filter. Use this to proxy requests to AWS KMS or
  Secrets Manager.
* `vault:hashicorp:<path>#<field>` (or any custom prefix) &mdash; handled via
  provider-specific filters (`tts_vault_resolve_hashicorp`, etc.).
* `env:NAME` &mdash; convenience helper to resolve values from environment
  variables.

Additional providers can be registered through the
`tts_resolve_managed_secret` filter.

## Token rotation

`TTS_Token_Refresh` coordinates scheduled token refresh operations. Enhancements
include:

* Automatic rotation when tokens are near expiry (within 24 hours by default).
* Rotation throttling to avoid unnecessary requests when tokens were refreshed
  recently.
* Safe fallbacks that retain the previous token value and update rotation
  metadata (`*_token_previous`, `*_token_rotated_at`, `*_token_expires_at`).
* Compatibility with vault-managed application secrets via the secure storage
  resolvers described above.

The refresh workflow raises the `tts_token_refresh_persist_token` filter to
allow custom storage backends to persist refreshed tokens.

## Security audit retention

Security audit log payloads are encrypted before insertion into the custom log
table and are automatically masked before being exposed to administrators. Log
retention defaults to the following policy (in days):

| Risk level | Retention |
|------------|-----------|
| Low        | 30        |
| Medium     | 90        |
| High       | 180       |
| Critical   | 365       |

The policy can be customised using the `tts_security_audit_retention_policy`
filter. Manual cleanup requests honour the maximum retention allowed by the
policy to ensure compliance with data governance requirements.
