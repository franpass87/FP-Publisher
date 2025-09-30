# Architecture overview

FP Publisher is structured around a modular service container with clear separation between infrastructure concerns and domain services.

## Key components

* **Loader** – boots the plugin, registers cron events, and wires the service container.
* **Infra** – database migrations, queue storage, and capability registration.
* **Services** – business logic such as connectors, approvals, alerts, and housekeeping.
* **Support** – helper utilities (dates, validation, transient error classifier, i18n).
* **Assets** – React SPA that powers the admin experience.

## Request lifecycle

1. WordPress loads `fp-digital-publisher.php` which instantiates the loader.
2. The loader registers activation/deactivation callbacks and initialises the container.
3. REST routes and admin menu entries are registered on the appropriate WordPress hooks.
4. Queue workers run via WP-Cron (`fp_pub_tick`) or an external runner hitting the dispatch endpoint.

## Capabilities

The plugin defines custom capabilities to gate access:

* `fp_pub_manage_connectors`
* `fp_pub_approve`
* `fp_pub_replay`
* `fp_pub_manage_settings`

Assign them to roles via the capabilities service or custom role management plugins.

## Extensibility points

Filters and actions allow developers to customise payloads, retry rules, and notification flows. See [hooks.md](hooks.md) for the full catalogue.
