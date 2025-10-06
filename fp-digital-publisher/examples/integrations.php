<?php
/**
 * FP Digital Publisher - Integration Examples
 *
 * This file contains practical examples of integrating FP Publisher
 * with external services like Slack, DataDog, PagerDuty, etc.
 *
 * Copy the examples you need into your theme's functions.php
 * or a custom plugin.
 */

declare(strict_types=1);

// ============================================================================
// SLACK INTEGRATION
// ============================================================================

/**
 * Send Slack notification when circuit breaker opens
 */
add_action('fp_publisher_circuit_breaker_opened', function($service, $stats) {
    $webhookUrl = 'https://hooks.slack.com/services/YOUR/WEBHOOK/URL';
    
    $message = [
        'text' => '⚠️ Circuit Breaker Alert',
        'attachments' => [
            [
                'color' => 'danger',
                'title' => 'Service Unavailable',
                'fields' => [
                    [
                        'title' => 'Service',
                        'value' => $service,
                        'short' => true
                    ],
                    [
                        'title' => 'State',
                        'value' => strtoupper($stats['state']),
                        'short' => true
                    ],
                    [
                        'title' => 'Failures',
                        'value' => (string) $stats['failures'],
                        'short' => true
                    ],
                    [
                        'title' => 'Last Error',
                        'value' => $stats['last_failure'] ?? 'Unknown',
                        'short' => false
                    ]
                ],
                'footer' => 'FP Publisher',
                'ts' => time()
            ]
        ]
    ];
    
    wp_remote_post($webhookUrl, [
        'blocking' => false,
        'headers' => ['Content-Type' => 'application/json'],
        'body' => wp_json_encode($message)
    ]);
}, 10, 2);

/**
 * Send Slack notification for DLQ items
 */
add_action('fp_publisher_job_moved_to_dlq', function($job, $error, $attempts) {
    $webhookUrl = 'https://hooks.slack.com/services/YOUR/WEBHOOK/URL';
    
    $message = [
        'text' => '💀 Job Moved to Dead Letter Queue',
        'attachments' => [
            [
                'color' => 'warning',
                'fields' => [
                    [
                        'title' => 'Job ID',
                        'value' => (string) ($job['id'] ?? 'unknown'),
                        'short' => true
                    ],
                    [
                        'title' => 'Channel',
                        'value' => $job['channel'] ?? 'unknown',
                        'short' => true
                    ],
                    [
                        'title' => 'Attempts',
                        'value' => (string) $attempts,
                        'short' => true
                    ],
                    [
                        'title' => 'Error',
                        'value' => substr($error, 0, 200),
                        'short' => false
                    ]
                ]
            ]
        ]
    ];
    
    wp_remote_post($webhookUrl, [
        'blocking' => false,
        'headers' => ['Content-Type' => 'application/json'],
        'body' => wp_json_encode($message)
    ]);
}, 10, 3);

// ============================================================================
// DATADOG INTEGRATION
// ============================================================================

/**
 * Send metrics to DataDog
 */
add_action('shutdown', function() {
    if (!defined('DATADOG_API_KEY')) {
        return;
    }
    
    $metrics = \FP\Publisher\Monitoring\Metrics::snapshot();
    
    $datadogMetrics = [];
    
    // Convert counters
    foreach ($metrics['counters'] as $key => $value) {
        $datadogMetrics[] = [
            'metric' => 'fp.publisher.' . $key,
            'points' => [[$metrics['timestamp'], $value]],
            'type' => 'count'
        ];
    }
    
    // Convert gauges
    foreach ($metrics['gauges'] as $key => $value) {
        $datadogMetrics[] = [
            'metric' => 'fp.publisher.' . $key,
            'points' => [[$metrics['timestamp'], $value]],
            'type' => 'gauge'
        ];
    }
    
    // Send to DataDog
    wp_remote_post('https://api.datadoghq.com/api/v1/series', [
        'blocking' => false,
        'headers' => [
            'DD-API-KEY' => DATADOG_API_KEY,
            'Content-Type' => 'application/json'
        ],
        'body' => wp_json_encode(['series' => $datadogMetrics])
    ]);
});

// ============================================================================
// PAGERDUTY INTEGRATION
// ============================================================================

/**
 * Send PagerDuty alert on circuit breaker open
 */
add_action('fp_publisher_circuit_breaker_opened', function($service, $stats) {
    if (!defined('PAGERDUTY_INTEGRATION_KEY')) {
        return;
    }
    
    $event = [
        'routing_key' => PAGERDUTY_INTEGRATION_KEY,
        'event_action' => 'trigger',
        'payload' => [
            'summary' => "Circuit Breaker Opened: {$service}",
            'severity' => 'error',
            'source' => get_site_url(),
            'component' => $service,
            'custom_details' => [
                'service' => $service,
                'state' => $stats['state'],
                'failures' => $stats['failures'],
                'last_failure' => $stats['last_failure']
            ]
        ]
    ];
    
    wp_remote_post('https://events.pagerduty.com/v2/enqueue', [
        'headers' => ['Content-Type' => 'application/json'],
        'body' => wp_json_encode($event)
    ]);
}, 10, 2);

// ============================================================================
// NEW RELIC INTEGRATION
// ============================================================================

/**
 * Send custom events to New Relic
 */
add_action('fp_pub_published', function($channel, $remoteId, $job) {
    if (!extension_loaded('newrelic')) {
        return;
    }
    
    newrelic_name_transaction('FP Publisher - Job Processed');
    newrelic_add_custom_parameter('job_id', $job['id']);
    newrelic_add_custom_parameter('channel', $channel);
    newrelic_add_custom_parameter('remote_id', $remoteId ?? 'none');
    newrelic_add_custom_parameter('attempts', $job['attempts'] ?? 1);
}, 10, 3);

// ============================================================================
// ELASTICSEARCH/LOGSTASH INTEGRATION
// ============================================================================

/**
 * Send structured logs to Elasticsearch
 */
add_action('fp_publisher_job_moved_to_dlq', function($job, $error, $attempts) {
    if (!defined('ELASTICSEARCH_HOST')) {
        return;
    }
    
    $logEntry = [
        '@timestamp' => gmdate('c'),
        'level' => 'error',
        'service' => 'fp-publisher',
        'event' => 'job_moved_to_dlq',
        'job_id' => $job['id'] ?? null,
        'channel' => $job['channel'] ?? null,
        'error' => $error,
        'attempts' => $attempts,
        'payload' => $job['payload'] ?? []
    ];
    
    wp_remote_post(ELASTICSEARCH_HOST . '/fp-publisher-logs/_doc', [
        'blocking' => false,
        'headers' => ['Content-Type' => 'application/json'],
        'body' => wp_json_encode($logEntry)
    ]);
}, 10, 3);

// ============================================================================
// CUSTOM CHANNEL DISPATCHER EXAMPLE
// ============================================================================

/**
 * Example: Custom LinkedIn dispatcher using circuit breaker
 */
add_action('fp_publisher_process_job', function($job) {
    $channel = $job['channel'] ?? '';
    
    if ($channel !== 'linkedin') {
        return; // Not our job
    }
    
    $circuitBreaker = new \FP\Publisher\Support\CircuitBreaker('linkedin_api', 5, 120);
    
    try {
        $result = $circuitBreaker->call(function() use ($job) {
            // Your LinkedIn API call here
            $response = wp_remote_post('https://api.linkedin.com/v2/ugcPosts', [
                'headers' => [
                    'Authorization' => 'Bearer ' . get_option('linkedin_access_token'),
                    'Content-Type' => 'application/json'
                ],
                'body' => wp_json_encode([
                    'author' => 'urn:li:person:' . get_option('linkedin_person_id'),
                    'lifecycleState' => 'PUBLISHED',
                    'specificContent' => [
                        'com.linkedin.ugc.ShareContent' => [
                            'shareCommentary' => [
                                'text' => $job['payload']['caption'] ?? ''
                            ],
                            'shareMediaCategory' => 'NONE'
                        ]
                    ],
                    'visibility' => [
                        'com.linkedin.ugc.MemberNetworkVisibility' => 'PUBLIC'
                    ]
                ])
            ]);
            
            if (is_wp_error($response)) {
                throw new \RuntimeException($response->get_error_message());
            }
            
            return json_decode(wp_remote_retrieve_body($response), true);
        });
        
        // Mark job as completed
        \FP\Publisher\Infra\Queue::markCompleted(
            $job['id'],
            $result['id'] ?? null
        );
        
        // Track metric
        \FP\Publisher\Monitoring\Metrics::incrementCounter('jobs_processed_total', 1, [
            'channel' => 'linkedin',
            'status' => 'success'
        ]);
        
    } catch (\FP\Publisher\Support\CircuitBreakerOpenException $e) {
        // Circuit is open, retry later
        \FP\Publisher\Infra\Queue::markFailed($job, $e->getMessage(), true);
    } catch (\Throwable $e) {
        // Other errors
        \FP\Publisher\Infra\Queue::markFailed($job, $e->getMessage(), false);
        
        \FP\Publisher\Monitoring\Metrics::incrementCounter('jobs_errors_total', 1, [
            'channel' => 'linkedin',
            'error_type' => get_class($e)
        ]);
    }
});

// ============================================================================
// WEBHOOK NOTIFICATIONS
// ============================================================================

/**
 * Send webhook on job completion
 */
add_action('fp_pub_published', function($channel, $remoteId, $job) {
    $webhookUrl = get_option('fp_publisher_webhook_url');
    
    if (!$webhookUrl) {
        return;
    }
    
    $payload = [
        'event' => 'job.completed',
        'timestamp' => gmdate('c'),
        'data' => [
            'job_id' => $job['id'],
            'channel' => $channel,
            'remote_id' => $remoteId,
            'idempotency_key' => $job['idempotency_key'] ?? null
        ]
    ];
    
    // Generate HMAC signature
    $secret = get_option('fp_publisher_webhook_secret');
    $signature = hash_hmac('sha256', wp_json_encode($payload), $secret);
    
    wp_remote_post($webhookUrl, [
        'blocking' => false,
        'headers' => [
            'Content-Type' => 'application/json',
            'X-FP-Publisher-Signature' => $signature,
            'X-FP-Publisher-Event' => 'job.completed'
        ],
        'body' => wp_json_encode($payload)
    ]);
}, 10, 3);

// ============================================================================
// GOOGLE ANALYTICS INTEGRATION
// ============================================================================

/**
 * Track publishing events in Google Analytics
 */
add_action('fp_pub_published', function($channel, $remoteId, $job) {
    $trackingId = get_option('ga_tracking_id');
    
    if (!$trackingId) {
        return;
    }
    
    // Send event to Google Analytics via Measurement Protocol
    wp_remote_post('https://www.google-analytics.com/collect', [
        'blocking' => false,
        'body' => [
            'v' => '1',
            'tid' => $trackingId,
            'cid' => wp_generate_uuid4(),
            't' => 'event',
            'ec' => 'publishing',
            'ea' => 'job_completed',
            'el' => $channel,
            'ev' => 1
        ]
    ]);
}, 10, 3);

// ============================================================================
// EMAIL DIGEST FOR ADMINS
// ============================================================================

/**
 * Send daily digest email with stats
 */
add_action('wp', function() {
    if (!wp_next_scheduled('fp_publisher_daily_digest')) {
        wp_schedule_event(time(), 'daily', 'fp_publisher_daily_digest');
    }
});

add_action('fp_publisher_daily_digest', function() {
    $stats = \FP\Publisher\Infra\DeadLetterQueue::getStats();
    $metrics = \FP\Publisher\Monitoring\Metrics::snapshot();
    
    $cbServices = ['meta_api', 'tiktok_api', 'youtube_api', 'google_business_api'];
    $openCircuits = [];
    
    foreach ($cbServices as $service) {
        $cb = new \FP\Publisher\Support\CircuitBreaker($service);
        if ($cb->isOpen()) {
            $openCircuits[] = $service;
        }
    }
    
    $subject = 'FP Publisher Daily Report - ' . date('Y-m-d');
    
    $body = "FP Digital Publisher - Daily Summary\n\n";
    $body .= "Date: " . date('Y-m-d H:i:s') . "\n\n";
    
    $body .= "=== QUEUE STATUS ===\n";
    $jobsProcessed = $metrics['counters']['jobs_processed_total'] ?? 0;
    $jobsErrors = $metrics['counters']['jobs_errors_total'] ?? 0;
    $body .= "Jobs Processed: {$jobsProcessed}\n";
    $body .= "Jobs Failed: {$jobsErrors}\n";
    if ($jobsProcessed > 0) {
        $errorRate = round(($jobsErrors / $jobsProcessed) * 100, 2);
        $body .= "Error Rate: {$errorRate}%\n";
    }
    $body .= "\n";
    
    $body .= "=== DEAD LETTER QUEUE ===\n";
    $body .= "Total Items: {$stats['total']}\n";
    $body .= "Added Today: {$stats['recent_24h']}\n";
    if (!empty($stats['by_channel'])) {
        $body .= "By Channel:\n";
        foreach ($stats['by_channel'] as $channel => $count) {
            $body .= "  - {$channel}: {$count}\n";
        }
    }
    $body .= "\n";
    
    $body .= "=== CIRCUIT BREAKERS ===\n";
    if (empty($openCircuits)) {
        $body .= "All services operational ✓\n";
    } else {
        $body .= "⚠️ OPEN CIRCUITS: " . implode(', ', $openCircuits) . "\n";
    }
    $body .= "\n";
    
    $body .= "View full details: " . admin_url('admin.php?page=fp-publisher') . "\n";
    
    $recipients = get_option('fp_publisher_admin_emails', [get_option('admin_email')]);
    
    foreach ($recipients as $email) {
        wp_mail($email, $subject, $body);
    }
});

// ============================================================================
// MICROSOFT TEAMS INTEGRATION
// ============================================================================

/**
 * Send Teams notification on critical events
 */
add_action('fp_publisher_circuit_breaker_opened', function($service, $stats) {
    $webhookUrl = get_option('fp_publisher_teams_webhook');
    
    if (!$webhookUrl) {
        return;
    }
    
    $message = [
        '@type' => 'MessageCard',
        '@context' => 'http://schema.org/extensions',
        'themeColor' => 'FF0000',
        'summary' => "Circuit Breaker Alert: {$service}",
        'sections' => [
            [
                'activityTitle' => '⚠️ Circuit Breaker Opened',
                'activitySubtitle' => $service,
                'facts' => [
                    ['name' => 'Service', 'value' => $service],
                    ['name' => 'State', 'value' => strtoupper($stats['state'])],
                    ['name' => 'Failures', 'value' => (string) $stats['failures']],
                    ['name' => 'Last Error', 'value' => substr($stats['last_failure'] ?? '', 0, 100)]
                ]
            ]
        ],
        'potentialAction' => [
            [
                '@type' => 'OpenUri',
                'name' => 'View Dashboard',
                'targets' => [
                    ['os' => 'default', 'uri' => admin_url('admin.php?page=fp-publisher')]
                ]
            ]
        ]
    ];
    
    wp_remote_post($webhookUrl, [
        'blocking' => false,
        'headers' => ['Content-Type' => 'application/json'],
        'body' => wp_json_encode($message)
    ]);
}, 10, 2);

// ============================================================================
// CUSTOM METRICS EXPORT
// ============================================================================

/**
 * Export metrics to custom endpoint every 5 minutes
 */
add_action('wp', function() {
    if (!wp_next_scheduled('fp_publisher_export_metrics')) {
        wp_schedule_event(time(), 'fp_pub_5min', 'fp_publisher_export_metrics');
    }
});

add_action('fp_publisher_export_metrics', function() {
    $endpoint = get_option('fp_publisher_metrics_endpoint');
    
    if (!$endpoint) {
        return;
    }
    
    $metrics = \FP\Publisher\Monitoring\Metrics::snapshot();
    
    // Add system info
    $metrics['system'] = [
        'site_url' => get_site_url(),
        'php_version' => PHP_VERSION,
        'wp_version' => get_bloginfo('version'),
        'plugin_version' => defined('FP_PUBLISHER_VERSION') ? FP_PUBLISHER_VERSION : 'unknown'
    ];
    
    wp_remote_post($endpoint, [
        'blocking' => false,
        'headers' => [
            'Content-Type' => 'application/json',
            'X-API-Key' => get_option('fp_publisher_metrics_api_key')
        ],
        'body' => wp_json_encode($metrics)
    ]);
});

// ============================================================================
// DISCORD INTEGRATION
// ============================================================================

/**
 * Send Discord notifications
 */
add_action('fp_publisher_job_moved_to_dlq', function($job, $error, $attempts) {
    $webhookUrl = get_option('fp_publisher_discord_webhook');
    
    if (!$webhookUrl) {
        return;
    }
    
    $message = [
        'content' => '⚠️ Job moved to Dead Letter Queue',
        'embeds' => [
            [
                'title' => 'Failed Job Alert',
                'color' => 15158332, // Red
                'fields' => [
                    ['name' => 'Job ID', 'value' => (string) ($job['id'] ?? 'unknown'), 'inline' => true],
                    ['name' => 'Channel', 'value' => $job['channel'] ?? 'unknown', 'inline' => true],
                    ['name' => 'Attempts', 'value' => (string) $attempts, 'inline' => true],
                    ['name' => 'Error', 'value' => '```' . substr($error, 0, 200) . '```', 'inline' => false]
                ],
                'timestamp' => gmdate('c')
            ]
        ]
    ];
    
    wp_remote_post($webhookUrl, [
        'blocking' => false,
        'headers' => ['Content-Type' => 'application/json'],
        'body' => wp_json_encode($message)
    ]);
}, 10, 3);

// ============================================================================
// SENTRY INTEGRATION
// ============================================================================

/**
 * Send errors to Sentry
 */
add_action('fp_publisher_job_moved_to_dlq', function($job, $error, $attempts) {
    if (!defined('SENTRY_DSN')) {
        return;
    }
    
    // Assuming Sentry PHP SDK is installed
    if (function_exists('\\Sentry\\captureMessage')) {
        \Sentry\captureMessage('Job moved to DLQ: ' . $error, \Sentry\Severity::error(), [
            'extra' => [
                'job_id' => $job['id'] ?? null,
                'channel' => $job['channel'] ?? null,
                'attempts' => $attempts,
                'payload' => $job['payload'] ?? []
            ],
            'tags' => [
                'component' => 'fp-publisher',
                'channel' => $job['channel'] ?? 'unknown'
            ]
        ]);
    }
}, 10, 3);

// ============================================================================
// ZENDESK TICKET CREATION
// ============================================================================

/**
 * Create Zendesk ticket for DLQ items
 */
add_action('fp_publisher_job_moved_to_dlq', function($job, $error, $attempts) {
    $zendeskDomain = get_option('zendesk_domain');
    $zendeskToken = get_option('zendesk_api_token');
    
    if (!$zendeskDomain || !$zendeskToken) {
        return;
    }
    
    $ticket = [
        'ticket' => [
            'subject' => "FP Publisher: Job #{$job['id']} failed permanently",
            'comment' => [
                'body' => "A publishing job has failed and been moved to the Dead Letter Queue.\n\n" .
                         "Job ID: {$job['id']}\n" .
                         "Channel: {$job['channel']}\n" .
                         "Attempts: {$attempts}\n" .
                         "Error: {$error}\n\n" .
                         "Please investigate and retry if appropriate."
            ],
            'priority' => 'normal',
            'tags' => ['fp-publisher', 'dlq', $job['channel'] ?? 'unknown']
        ]
    ];
    
    wp_remote_post("https://{$zendeskDomain}.zendesk.com/api/v2/tickets.json", [
        'headers' => [
            'Content-Type' => 'application/json',
            'Authorization' => 'Basic ' . base64_encode($zendeskToken)
        ],
        'body' => wp_json_encode($ticket)
    ]);
}, 10, 3);

// ============================================================================
// CUSTOM METRICS DASHBOARD
// ============================================================================

/**
 * Add custom admin dashboard widget with metrics
 */
add_action('wp_dashboard_setup', function() {
    wp_add_dashboard_widget(
        'fp_publisher_metrics_widget',
        'FP Publisher Metrics',
        function() {
            $metrics = \FP\Publisher\Monitoring\Metrics::snapshot();
            $dlqStats = \FP\Publisher\Infra\DeadLetterQueue::getStats();
            
            echo '<div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px;">';
            
            // Jobs processed
            $jobsProcessed = $metrics['counters']['jobs_processed_total'] ?? 0;
            echo '<div style="padding: 10px; background: #f0f0f1; border-radius: 4px;">';
            echo '<strong>Jobs Processed</strong><br>';
            echo '<span style="font-size: 24px; color: #2271b1;">' . number_format($jobsProcessed) . '</span>';
            echo '</div>';
            
            // DLQ items
            echo '<div style="padding: 10px; background: #f0f0f1; border-radius: 4px;">';
            echo '<strong>DLQ Items</strong><br>';
            $color = $dlqStats['total'] > 50 ? '#d63638' : '#00a32a';
            echo '<span style="font-size: 24px; color: ' . $color . ';">' . $dlqStats['total'] . '</span>';
            echo '</div>';
            
            // Circuit breakers
            $services = ['meta_api', 'tiktok_api', 'youtube_api', 'google_business_api'];
            $openCount = 0;
            foreach ($services as $service) {
                $cb = new \FP\Publisher\Support\CircuitBreaker($service);
                if ($cb->isOpen()) {
                    $openCount++;
                }
            }
            
            echo '<div style="padding: 10px; background: #f0f0f1; border-radius: 4px;">';
            echo '<strong>Circuit Breakers</strong><br>';
            $color = $openCount > 0 ? '#d63638' : '#00a32a';
            echo '<span style="font-size: 24px; color: ' . $color . ';">' . $openCount . ' / 4</span> open';
            echo '</div>';
            
            // Avg processing time
            $avgTime = $metrics['histograms']['job_processing_duration_ms']['avg'] ?? 0;
            echo '<div style="padding: 10px; background: #f0f0f1; border-radius: 4px;">';
            echo '<strong>Avg Processing Time</strong><br>';
            echo '<span style="font-size: 24px; color: #2271b1;">' . round($avgTime, 1) . 'ms</span>';
            echo '</div>';
            
            echo '</div>';
            
            echo '<p style="margin-top: 15px; text-align: center;">';
            echo '<a href="' . admin_url('admin.php?page=fp-publisher') . '" class="button button-primary">View Dashboard</a>';
            echo '</p>';
        }
    );
});
