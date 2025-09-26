# Phase 7 – Refactoring

## Goals
- Centralize plugin bootstrapping to eliminate duplicated hook wiring and clarify execution order.
- Reduce global state by routing service access through a dedicated bootstrap coordinator.
- Maintain backwards compatibility for existing procedural helpers while delegating their work to the new orchestrator.

## Changes
- Introduced `TTS_Plugin_Bootstrap` to load dependencies, register hooks, and manage the shared service container in a single location.
- Replaced the large anonymous `plugins_loaded` closure with descriptive methods that load includes, register admin controllers, and configure recurring events.
- Updated existing helper functions (`tsap_*`) to proxy into the bootstrap class so external integrations continue to operate without modification.
- Ensured non-WordPress contexts (e.g., CLI tools) still initialise the plugin by falling back to direct bootstrap execution when core hooks are unavailable.

## Follow-up
- Evaluate splitting lengthy closures inside the bootstrap class into dedicated classes as further clean-up.
- Consider moving cron/task registration into specialised scheduler classes to improve testability.
