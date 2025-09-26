# Phase 9 – Upgrade & Migrations

## Summary

- Introduced the `TTS_Plugin_Upgrades` orchestrator to persist the installed version, run incremental migrations, and flush runtime caches after each upgrade.
- Added multisite-aware option migration so network activations move existing `tts_` options (including the stored plugin version) into the site-wide table.
- Wired the bootstrapper and activation flow to execute the upgrade runner before services initialise, ensuring schema installers and cache resets occur during deploys.

## Outstanding Follow-ups

- Expand automated migration coverage for third-party integrations whose options are not prefixed with `tts_`.
- Evaluate whether Action Scheduler queues require deduplication during major upgrades when background jobs change structure.
