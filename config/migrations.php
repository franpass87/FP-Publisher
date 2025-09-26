<?php

return [
    'options' => [
        'tts_settings' => [
            'source'    => ['type' => 'option', 'key' => 'tts_settings'],
            'target'    => ['type' => 'option', 'key' => 'fp_publisher.settings.core'],
            'transform' => 'fp_publisher\Migrator\Transforms::splitSettingsPayload',
            'notes'     => 'Maps nested settings array into modular config buckets (integrations, scheduling, templates, media).',
            'rollback'  => [
                'strategy' => 'restore_snapshot',
                'source'   => ['type' => 'option', 'key' => 'tts_settings'],
                'steps'    => [
                    'merge_new_segments_back_into_legacy_array',
                    'write_snapshot_to_option',
                ],
            ],
        ],
        'tts_social_apps' => [
            'source'    => ['type' => 'option', 'key' => 'tts_social_apps'],
            'target'    => ['type' => 'option', 'key' => 'fp_publisher.integrations.registry'],
            'transform' => 'fp_publisher\Migrator\Transforms::normaliseOauthBundles',
            'notes'     => 'Renames provider keys and extracts refresh metadata into `fp_publisher_tokens` table rows.',
            'rollback'  => [
                'strategy' => 'rebuild_from_tokens_table',
                'source'   => ['type' => 'option', 'key' => 'tts_social_apps'],
                'steps'    => [
                    'collect_tokens_for_each_provider',
                    'reconstruct_legacy_option_structure',
                    'persist_option_with_wp_update_option',
                ],
            ],
        ],
        'tts_trello_enabled' => [
            'source'    => ['type' => 'option', 'key' => 'tts_trello_enabled'],
            'target'    => ['type' => 'option', 'key' => 'fp_publisher.flags.trello.enabled'],
            'transform' => 'fp_publisher\Migrator\Transforms::castBooleanFlag',
            'rollback'  => [
                'strategy' => 'simple_option_restore',
                'source'   => ['type' => 'option', 'key' => 'tts_trello_enabled'],
            ],
        ],
        'tts_quickstart_last_package' => [
            'source'    => ['type' => 'option', 'key' => 'tts_quickstart_last_package'],
            'target'    => ['type' => 'option', 'key' => 'fp_publisher.onboarding.last_package'],
            'transform' => 'fp_publisher\Migrator\Transforms::identity',
            'rollback'  => [
                'strategy' => 'simple_option_restore',
                'source'   => ['type' => 'option', 'key' => 'tts_quickstart_last_package'],
            ],
        ],
        'tts_last_health_check' => [
            'source'    => ['type' => 'option', 'key' => 'tts_last_health_check'],
            'target'    => ['type' => 'table', 'name' => 'fp_publisher_health_snapshots'],
            'transform' => 'fp_publisher\Migrator\Transforms::appendHealthSnapshotRow',
            'notes'     => 'Each migration writes a row with `captured_at`, `score`, and JSON encoded checks/alerts.',
            'rollback'  => [
                'strategy' => 'truncate_table_restore_option',
                'source'   => ['type' => 'option', 'key' => 'tts_last_health_check'],
                'steps'    => [
                    'export_latest_snapshot_from_table',
                    'write_payload_back_to_option',
                ],
            ],
        ],
        'tts_alert_settings' => [
            'source'    => ['type' => 'option', 'key' => 'tts_alert_settings'],
            'target'    => ['type' => 'option', 'key' => 'fp_publisher.alerts.settings'],
            'transform' => 'fp_publisher\Migrator\Transforms::renameAlertKeys',
            'rollback'  => [
                'strategy' => 'simple_option_restore',
                'source'   => ['type' => 'option', 'key' => 'tts_alert_settings'],
            ],
        ],
        'tts_slack_webhook' => [
            'source'    => ['type' => 'option', 'key' => 'tts_slack_webhook'],
            'target'    => ['type' => 'option', 'key' => 'fp_publisher.notifications.slack_webhook'],
            'transform' => 'fp_publisher\Migrator\Transforms::identity',
            'rollback'  => [
                'strategy' => 'simple_option_restore',
                'source'   => ['type' => 'option', 'key' => 'tts_slack_webhook'],
            ],
        ],
        'tts_retry_queue' => [
            'source'    => ['type' => 'option', 'key' => 'tts_retry_queue'],
            'target'    => ['type' => 'table', 'name' => 'fp_publisher_queue_failures'],
            'transform' => 'fp_publisher\Migrator\Transforms::expandRetryQueueRows',
            'notes'     => 'Creates one row per failed item with channel, attempt counts, and payload JSON.',
            'rollback'  => [
                'strategy' => 'rebuild_queue_array',
                'source'   => ['type' => 'option', 'key' => 'tts_retry_queue'],
                'steps'    => [
                    'collect_rows_by_post_and_channel',
                    'serialise_back_into_legacy_array',
                    'persist_option',
                ],
            ],
        ],
        'tts_error_logs' => [
            'source'    => ['type' => 'option', 'key' => 'tts_error_logs'],
            'target'    => ['type' => 'table', 'name' => 'fp_publisher_error_logs'],
            'transform' => 'fp_publisher\Migrator\Transforms::expandErrorLogRows',
            'rollback'  => [
                'strategy' => 'rebuild_error_option',
                'source'   => ['type' => 'option', 'key' => 'tts_error_logs'],
                'steps'    => [
                    'fetch_rows_sorted_by_created_at',
                    'serialise_rows_to_option_payload',
                ],
            ],
        ],
        'tts_channel_limits' => [
            'source'    => ['type' => 'option', 'key' => 'tts_channel_limits'],
            'target'    => ['type' => 'option', 'key' => 'fp_publisher.channels.limits'],
            'transform' => 'fp_publisher\Migrator\Transforms::normaliseChannelLimits',
            'rollback'  => [
                'strategy' => 'simple_option_restore',
                'source'   => ['type' => 'option', 'key' => 'tts_channel_limits'],
            ],
        ],
        'tts_api_request_logs' => [
            'source'    => ['type' => 'option', 'key' => 'tts_api_request_logs'],
            'target'    => ['type' => 'table', 'name' => 'fp_publisher_rate_limit_events'],
            'transform' => 'fp_publisher\Migrator\Transforms::expandRateLimitRows',
            'rollback'  => [
                'strategy' => 'serialise_rows_to_option',
                'source'   => ['type' => 'option', 'key' => 'tts_api_request_logs'],
                'steps'    => [
                    'group_rows_by_channel',
                    'truncate_table',
                ],
            ],
        ],
        'tts_blocked_ips' => [
            'source'    => ['type' => 'option', 'key' => 'tts_blocked_ips'],
            'target'    => ['type' => 'option', 'key' => 'fp_publisher.security.blocked_ips'],
            'transform' => 'fp_publisher\Migrator\Transforms::normaliseBlockedIps',
            'rollback'  => [
                'strategy' => 'simple_option_restore',
                'source'   => ['type' => 'option', 'key' => 'tts_blocked_ips'],
            ],
        ],
        'tts_google_drive_settings' => [
            'source'    => ['type' => 'option', 'key' => 'tts_google_drive_settings'],
            'target'    => ['type' => 'option', 'key' => 'fp_publisher.importers.google_drive.settings'],
            'transform' => 'fp_publisher\Migrator\Transforms::normaliseCloudImporterSettings',
            'rollback'  => [
                'strategy' => 'simple_option_restore',
                'source'   => ['type' => 'option', 'key' => 'tts_google_drive_settings'],
            ],
        ],
        'tts_google_drive_access_token' => [
            'source'    => ['type' => 'option', 'key' => 'tts_google_drive_access_token'],
            'target'    => ['type' => 'secret', 'key' => 'fp_publisher.secrets.google_drive.access_token'],
            'transform' => 'fp_publisher\Migrator\Transforms::rehashSecret',
            'rollback'  => [
                'strategy' => 'restore_plain_secret',
                'source'   => ['type' => 'option', 'key' => 'tts_google_drive_access_token'],
            ],
        ],
        'tts_google_drive_folder_id' => [
            'source'    => ['type' => 'option', 'key' => 'tts_google_drive_folder_id'],
            'target'    => ['type' => 'option', 'key' => 'fp_publisher.importers.google_drive.folder_id'],
            'transform' => 'fp_publisher\Migrator\Transforms::identity',
            'rollback'  => [
                'strategy' => 'simple_option_restore',
                'source'   => ['type' => 'option', 'key' => 'tts_google_drive_folder_id'],
            ],
        ],
        'tts_dropbox_settings' => [
            'source'    => ['type' => 'option', 'key' => 'tts_dropbox_settings'],
            'target'    => ['type' => 'option', 'key' => 'fp_publisher.importers.dropbox.settings'],
            'transform' => 'fp_publisher\Migrator\Transforms::normaliseCloudImporterSettings',
            'rollback'  => [
                'strategy' => 'simple_option_restore',
                'source'   => ['type' => 'option', 'key' => 'tts_dropbox_settings'],
            ],
        ],
        'tts_dropbox_access_token' => [
            'source'    => ['type' => 'option', 'key' => 'tts_dropbox_access_token'],
            'target'    => ['type' => 'secret', 'key' => 'fp_publisher.secrets.dropbox.access_token'],
            'transform' => 'fp_publisher\Migrator\Transforms::rehashSecret',
            'rollback'  => [
                'strategy' => 'restore_plain_secret',
                'source'   => ['type' => 'option', 'key' => 'tts_dropbox_access_token'],
            ],
        ],
        'tts_dropbox_folder_path' => [
            'source'    => ['type' => 'option', 'key' => 'tts_dropbox_folder_path'],
            'target'    => ['type' => 'option', 'key' => 'fp_publisher.importers.dropbox.folder_path'],
            'transform' => 'fp_publisher\Migrator\Transforms::identity',
            'rollback'  => [
                'strategy' => 'simple_option_restore',
                'source'   => ['type' => 'option', 'key' => 'tts_dropbox_folder_path'],
            ],
        ],
        'tts_managed_credentials' => [
            'source'    => ['type' => 'option', 'key' => 'tts_managed_credentials'],
            'target'    => ['type' => 'table', 'name' => 'fp_publisher_secret_references'],
            'transform' => 'fp_publisher\Migrator\Transforms::expandManagedCredentials',
            'rollback'  => [
                'strategy' => 'rebuild_option_map',
                'source'   => ['type' => 'option', 'key' => 'tts_managed_credentials'],
                'steps'    => [
                    'group_rows_by_reference_key',
                    'serialise_rows_back_to_option',
                ],
            ],
        ],
        'tts_profiler_stats' => [
            'source'    => ['type' => 'option', 'key' => 'tts_profiler_stats'],
            'target'    => ['type' => 'table', 'name' => 'fp_publisher_profiler_snapshots'],
            'transform' => 'fp_publisher\Migrator\Transforms::expandProfilerRows',
            'rollback'  => [
                'strategy' => 'serialise_rows_to_option',
                'source'   => ['type' => 'option', 'key' => 'tts_profiler_stats'],
            ],
        ],
        'tts_youtube_daily_usage' => [
            'source'    => ['type' => 'option', 'key' => 'tts_youtube_daily_usage'],
            'target'    => ['type' => 'table', 'name' => 'fp_publisher_quota_daily_usage'],
            'transform' => 'fp_publisher\Migrator\Transforms::appendQuotaUsageRow',
            'rollback'  => [
                'strategy' => 'restore_numeric_option',
                'source'   => ['type' => 'option', 'key' => 'tts_youtube_daily_usage'],
            ],
        ],
        'tts_first_activation' => [
            'source'    => ['type' => 'option', 'key' => 'tts_first_activation'],
            'target'    => ['type' => 'option', 'key' => 'fp_publisher.telemetry.first_activation'],
            'transform' => 'fp_publisher\Migrator\Transforms::identity',
            'rollback'  => [
                'strategy' => 'simple_option_restore',
                'source'   => ['type' => 'option', 'key' => 'tts_first_activation'],
            ],
        ],
        'tts_integration_hub_db_version' => [
            'source'    => ['type' => 'option', 'key' => 'tts_integration_hub_db_version'],
            'target'    => ['type' => 'option', 'key' => 'fp_publisher.integration_hub.schema_version'],
            'transform' => 'fp_publisher\Migrator\Transforms::identity',
            'rollback'  => [
                'strategy' => 'simple_option_restore',
                'source'   => ['type' => 'option', 'key' => 'tts_integration_hub_db_version'],
            ],
        ],
    ],
    'option_prefixes' => [
        'tts_daily_report_' => [
            'source'    => ['type' => 'option_prefix', 'prefix' => 'tts_daily_report_'],
            'target'    => ['type' => 'table', 'name' => 'fp_publisher_health_reports'],
            'transform' => 'fp_publisher\Migrator\Transforms::migrateDailyReportOption',
            'notes'     => 'Transforms each dated option into a row with report date and JSON payload.',
            'rollback'  => [
                'strategy' => 'bulk_option_restore',
                'source'   => ['type' => 'option_prefix', 'prefix' => 'tts_daily_report_'],
                'steps'    => [
                    'export_rows_ordered_by_report_date',
                    'recreate_prefixed_options',
                ],
            ],
        ],
        'tts_quota_' => [
            'source'    => ['type' => 'option_prefix', 'prefix' => 'tts_quota_'],
            'target'    => ['type' => 'table', 'name' => 'fp_publisher_quota_counters'],
            'transform' => 'fp_publisher\Migrator\Transforms::migrateQuotaCounters',
            'rollback'  => [
                'strategy' => 'bulk_option_restore',
                'source'   => ['type' => 'option_prefix', 'prefix' => 'tts_quota_'],
                'steps'    => [
                    're-hydrate_options_from_counter_rows',
                ],
            ],
        ],
    ],
    'tables' => [
        'tts_logs' => [
            'source'    => ['type' => 'table', 'name' => 'tts_logs'],
            'target'    => ['type' => 'table', 'name' => 'fp_publisher_activity_logs'],
            'transform' => [
                'column_map' => [
                    'id'          => 'id',
                    'timestamp'   => 'logged_at',
                    'level'       => 'severity',
                    'channel'     => 'channel',
                    'message'     => 'summary',
                    'context'     => 'context_json',
                    'request'     => 'request_json',
                    'response'    => 'response_json',
                    'post_id'     => 'related_post_id',
                ],
            ],
            'rollback'  => [
                'strategy' => 'swap_table_alias',
                'steps'    => [
                    'rename_new_table_to_temp',
                    'rename_legacy_table_back',
                    'restore_indexes',
                ],
            ],
        ],
        'tts_workflows' => [
            'source'    => [
                'type'  => 'table_group',
                'tables' => [
                    'tts_workflows',
                    'tts_workflow_comments',
                    'tts_workflow_templates',
                    'tts_workflow_assignments',
                ],
            ],
            'target'    => ['type' => 'schema', 'name' => 'fp_publisher_workflows'],
            'transform' => 'fp_publisher\Migrator\Transforms::migrateWorkflowSchema',
            'notes'     => 'Consolidates workflow tables into namespace with UUID primary keys and explicit foreign keys.',
            'rollback'  => [
                'strategy' => 'restore_workflow_dump',
                'steps'    => [
                    'drop_new_schema_tables',
                    'import_dump_created_pre_migration',
                ],
            ],
        ],
        'tts_integrations' => [
            'source'    => [
                'type'  => 'table_group',
                'tables' => [
                    'tts_integrations',
                    'tts_integration_data',
                ],
            ],
            'target'    => ['type' => 'schema', 'name' => 'fp_publisher_integrations'],
            'transform' => 'fp_publisher\Migrator\Transforms::migrateIntegrationHubSchema',
            'rollback'  => [
                'strategy' => 'restore_integration_dump',
                'steps'    => [
                    'drop_new_schema_tables',
                    'import_dump_created_pre_migration',
                ],
            ],
        ],
        'tts_competitor_insights' => [
            'source'    => ['type' => 'table', 'name' => 'tts_competitor_insights'],
            'target'    => ['type' => 'table', 'name' => 'fp_publisher_competitor_insights'],
            'transform' => 'fp_publisher\Migrator\Transforms::migrateCompetitorInsights',
            'rollback'  => [
                'strategy' => 'swap_table_alias',
            ],
        ],
        'tts_cache' => [
            'source'    => ['type' => 'table', 'name' => 'tts_cache'],
            'target'    => ['type' => 'table', 'name' => 'fp_publisher_cache'],
            'transform' => 'fp_publisher\Migrator\Transforms::migrateCacheRecords',
            'rollback'  => [
                'strategy' => 'truncate_new_table_restore_cache',
                'steps'    => [
                    'export_rows_from_new_table',
                    'drop_new_table',
                    'recreate_legacy_table_schema',
                    'bulk_insert_exported_rows',
                ],
            ],
        ],
    ],
];
