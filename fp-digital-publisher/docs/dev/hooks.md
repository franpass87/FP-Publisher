# Hooks reference

This page lists the public actions and filters provided by FP Publisher. Use them to extend connector behaviour, adjust retry policies, or integrate with external systems.

## Actions

### `fp_publisher_process_job`

Dispatched by the background worker for every runnable job. Attach callbacks here to observe or replace the default channel dispatchers.

**Parameters:**

1. `array<string, mixed> $job`

### `fp_pub_published`

Fires when a job completes successfully (either via native WordPress publishing or a remote connector). It is executed after the queue row is marked as completed, so third-party code can track remote IDs or trigger follow-up automations.

**Parameters:**

1. `string $channel` — normalized channel slug (for example `meta_facebook`, `tiktok`, `wordpress_blog`).
2. `string|null $remote_id` — remote identifier returned by the connector, if available.
3. `array<string, mixed> $job` — the original job payload.

### `fp_publisher_ig_first_comment_error`

Emitted when the Instagram first-comment helper cannot enqueue or publish a comment.

**Parameters:**

1. `array{job_id:int,message:string} $context`

## Filters

### `fp_pub_payload_pre_send`

Runs immediately before a job payload is handed to a connector. Returning a modified array allows integrations to enrich metadata or adjust API parameters dynamically.

**Parameters:**

1. `array<string, mixed> $payload`
2. `array<string, mixed> $job`

**Return:** `array<string, mixed>` filtered payload. Non-array values are ignored and the original payload is used instead.

### `fp_pub_retry_decision`

Gives extensions control over whether a failure should be retried. The default boolean comes from the transient error classifier and connector-specific heuristics; returning your own boolean overrides the behaviour.

**Parameters:**

1. `bool $retryable`
2. `\Throwable $exception`
3. `array<string, mixed> $job`

### `fp_publisher_role_capabilities`

Filter capabilities assigned to the custom FP Publisher role during bootstrap.

**Parameters:**

1. `array<string, bool> $capabilities`
2. `string $role`

### `fp_publisher_assets_ttl`

Customize the time-to-live (in minutes) for uploaded assets before the housekeeping job purges them.

**Parameters:**

1. `int $ttl`

**Return:** `int` new TTL value.
