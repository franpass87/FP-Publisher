<?php

declare(strict_types=1);

namespace FP\Publisher\Api;

use WP_REST_Response;

use function add_action;
use function admin_url;
use function current_user_can;
use function plugins_url;
use function register_rest_route;
use function rest_url;

/**
 * OpenAPI/Swagger specification generator
 */
final class OpenApiSpec
{
    public static function register(): void
    {
        add_action('rest_api_init', [self::class, 'registerRoutes']);
        add_action('admin_menu', [self::class, 'registerAdminPage']);
    }

    public static function registerRoutes(): void
    {
        register_rest_route('fp-publisher/v1', '/openapi', [
            'methods' => 'GET',
            'callback' => [self::class, 'getSpec'],
            'permission_callback' => '__return_true'
        ]);
    }

    public static function registerAdminPage(): void
    {
        // Use same capability logic as main menu for consistency
        $capability = current_user_can('fp_publisher_manage_plans') ? 'fp_publisher_manage_plans' : 'manage_options';
        
        add_submenu_page(
            'fp-publisher',
            'API Documentation',
            'API Docs',
            $capability,
            'fp-publisher-api-docs',
            [self::class, 'renderDocsPage']
        );
    }

    public static function getSpec(): WP_REST_Response
    {
        $spec = [
            'openapi' => '3.0.0',
            'info' => [
                'title' => 'FP Digital Publisher API',
                'version' => defined('FP_PUBLISHER_VERSION') ? FP_PUBLISHER_VERSION : '1.0.0',
                'description' => 'REST API for managing omnichannel social media publishing with queue-driven workflows',
                'contact' => [
                    'name' => 'Francesco Passeri',
                    'email' => 'info@francescopasseri.com',
                    'url' => 'https://francescopasseri.com'
                ],
                'license' => [
                    'name' => 'MIT',
                    'url' => 'https://opensource.org/licenses/MIT'
                ]
            ],
            'servers' => [
                ['url' => rest_url('fp-publisher/v1'), 'description' => 'Current site']
            ],
            'tags' => [
                ['name' => 'Health', 'description' => 'System health and monitoring'],
                ['name' => 'Queue', 'description' => 'Job queue management'],
                ['name' => 'Plans', 'description' => 'Publishing plans'],
                ['name' => 'Metrics', 'description' => 'System metrics'],
                ['name' => 'DLQ', 'description' => 'Dead Letter Queue'],
            ],
            'paths' => self::getPaths(),
            'components' => self::getComponents()
        ];

        return new WP_REST_Response($spec, 200);
    }

    private static function getPaths(): array
    {
        return [
            '/health' => [
                'get' => [
                    'tags' => ['Health'],
                    'summary' => 'System health check',
                    'description' => 'Check overall system health including database, queue, cron, and storage',
                    'parameters' => [
                        [
                            'name' => 'detailed',
                            'in' => 'query',
                            'description' => 'Include detailed metrics',
                            'schema' => ['type' => 'boolean', 'default' => false]
                        ]
                    ],
                    'responses' => [
                        '200' => [
                            'description' => 'System is healthy',
                            'content' => [
                                'application/json' => [
                                    'schema' => ['$ref' => '#/components/schemas/HealthResponse']
                                ]
                            ]
                        ],
                        '503' => [
                            'description' => 'System is unhealthy',
                            'content' => [
                                'application/json' => [
                                    'schema' => ['$ref' => '#/components/schemas/HealthResponse']
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            '/metrics' => [
                'get' => [
                    'tags' => ['Metrics'],
                    'summary' => 'Get system metrics',
                    'description' => 'Export metrics in JSON or Prometheus format',
                    'security' => [['BearerAuth' => []]],
                    'parameters' => [
                        [
                            'name' => 'format',
                            'in' => 'query',
                            'schema' => ['type' => 'string', 'enum' => ['json', 'prometheus'], 'default' => 'json']
                        ]
                    ],
                    'responses' => [
                        '200' => [
                            'description' => 'Metrics data',
                            'content' => [
                                'application/json' => [
                                    'schema' => ['$ref' => '#/components/schemas/MetricsResponse']
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            '/jobs/bulk' => [
                'post' => [
                    'tags' => ['Queue'],
                    'summary' => 'Bulk operations on jobs',
                    'description' => 'Perform bulk actions (replay, cancel, delete) on multiple jobs',
                    'security' => [['CookieAuth' => []], ['NonceAuth' => []]],
                    'requestBody' => [
                        'required' => true,
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/BulkJobRequest']
                            ]
                        ]
                    ],
                    'responses' => [
                        '200' => [
                            'description' => 'Bulk operation completed',
                            'content' => [
                                'application/json' => [
                                    'schema' => ['$ref' => '#/components/schemas/BulkJobResponse']
                                ]
                            ]
                        ],
                        '400' => ['description' => 'Invalid request'],
                        '429' => ['description' => 'Rate limit exceeded']
                    ]
                ]
            ],
            '/dlq' => [
                'get' => [
                    'tags' => ['DLQ'],
                    'summary' => 'List Dead Letter Queue items',
                    'security' => [['CookieAuth' => []], ['NonceAuth' => []]],
                    'parameters' => [
                        ['name' => 'page', 'in' => 'query', 'schema' => ['type' => 'integer', 'default' => 1]],
                        ['name' => 'per_page', 'in' => 'query', 'schema' => ['type' => 'integer', 'default' => 20]],
                        ['name' => 'channel', 'in' => 'query', 'schema' => ['type' => 'string']],
                        ['name' => 'search', 'in' => 'query', 'schema' => ['type' => 'string']],
                    ],
                    'responses' => [
                        '200' => [
                            'description' => 'DLQ items list',
                            'content' => [
                                'application/json' => [
                                    'schema' => ['$ref' => '#/components/schemas/DLQListResponse']
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            '/dlq/{id}/retry' => [
                'post' => [
                    'tags' => ['DLQ'],
                    'summary' => 'Retry job from DLQ',
                    'security' => [['CookieAuth' => []], ['NonceAuth' => []]],
                    'parameters' => [
                        [
                            'name' => 'id',
                            'in' => 'path',
                            'required' => true,
                            'schema' => ['type' => 'integer']
                        ]
                    ],
                    'responses' => [
                        '200' => [
                            'description' => 'Job successfully retried',
                            'content' => [
                                'application/json' => [
                                    'schema' => ['$ref' => '#/components/schemas/DLQRetryResponse']
                                ]
                            ]
                        ],
                        '400' => ['description' => 'Invalid DLQ item ID']
                    ]
                ]
            ]
        ];
    }

    private static function getComponents(): array
    {
        return [
            'securitySchemes' => [
                'BearerAuth' => [
                    'type' => 'http',
                    'scheme' => 'bearer',
                    'description' => 'Metrics token for monitoring endpoints'
                ],
                'CookieAuth' => [
                    'type' => 'apiKey',
                    'in' => 'cookie',
                    'name' => 'wordpress_logged_in',
                    'description' => 'WordPress authentication cookie'
                ],
                'NonceAuth' => [
                    'type' => 'apiKey',
                    'in' => 'header',
                    'name' => 'X-WP-Nonce',
                    'description' => 'WordPress nonce for CSRF protection'
                ]
            ],
            'schemas' => [
                'HealthResponse' => [
                    'type' => 'object',
                    'properties' => [
                        'status' => ['type' => 'string', 'enum' => ['healthy', 'unhealthy']],
                        'timestamp' => ['type' => 'string', 'format' => 'date-time'],
                        'checks' => [
                            'type' => 'object',
                            'properties' => [
                                'database' => ['$ref' => '#/components/schemas/HealthCheck'],
                                'queue' => ['$ref' => '#/components/schemas/HealthCheck'],
                                'cron' => ['$ref' => '#/components/schemas/HealthCheck'],
                                'storage' => ['$ref' => '#/components/schemas/HealthCheck']
                            ]
                        ]
                    ]
                ],
                'HealthCheck' => [
                    'type' => 'object',
                    'properties' => [
                        'healthy' => ['type' => 'boolean'],
                        'message' => ['type' => 'string'],
                        'metrics' => ['type' => 'object']
                    ]
                ],
                'MetricsResponse' => [
                    'type' => 'object',
                    'properties' => [
                        'counters' => ['type' => 'object'],
                        'gauges' => ['type' => 'object'],
                        'histograms' => ['type' => 'object'],
                        'timestamp' => ['type' => 'integer']
                    ]
                ],
                'BulkJobRequest' => [
                    'type' => 'object',
                    'required' => ['action', 'job_ids'],
                    'properties' => [
                        'action' => ['type' => 'string', 'enum' => ['replay', 'cancel', 'delete']],
                        'job_ids' => ['type' => 'array', 'items' => ['type' => 'integer'], 'maxItems' => 100]
                    ]
                ],
                'BulkJobResponse' => [
                    'type' => 'object',
                    'properties' => [
                        'action' => ['type' => 'string'],
                        'processed' => ['type' => 'integer'],
                        'results' => [
                            'type' => 'object',
                            'properties' => [
                                'success' => ['type' => 'array', 'items' => ['type' => 'integer']],
                                'failed' => [
                                    'type' => 'array',
                                    'items' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'id' => ['type' => 'integer'],
                                            'error' => ['type' => 'string']
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ],
                'DLQListResponse' => [
                    'type' => 'object',
                    'properties' => [
                        'items' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/DLQItem']],
                        'total' => ['type' => 'integer'],
                        'page' => ['type' => 'integer'],
                        'per_page' => ['type' => 'integer'],
                        'total_pages' => ['type' => 'integer']
                    ]
                ],
                'DLQItem' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'integer'],
                        'original_job_id' => ['type' => 'integer'],
                        'channel' => ['type' => 'string'],
                        'final_error' => ['type' => 'string'],
                        'total_attempts' => ['type' => 'integer'],
                        'moved_to_dlq_at' => ['type' => 'string', 'format' => 'date-time']
                    ]
                ],
                'DLQRetryResponse' => [
                    'type' => 'object',
                    'properties' => [
                        'message' => ['type' => 'string'],
                        'job' => ['$ref' => '#/components/schemas/Job']
                    ]
                ],
                'Job' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'integer'],
                        'status' => ['type' => 'string', 'enum' => ['pending', 'running', 'completed', 'failed']],
                        'channel' => ['type' => 'string'],
                        'run_at' => ['type' => 'string', 'format' => 'date-time'],
                        'attempts' => ['type' => 'integer'],
                        'idempotency_key' => ['type' => 'string'],
                        'child_job_id' => ['type' => 'integer', 'nullable' => true]
                    ]
                ]
            ]
        ];

        return $spec;
    }

    public static function renderDocsPage(): void
    {
        $specUrl = rest_url('fp-publisher/v1/openapi');
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>FP Publisher API Documentation</title>
            <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5/swagger-ui.css">
            <style>
                body { margin: 0; padding: 0; }
                .swagger-ui .topbar { display: none; }
            </style>
        </head>
        <body>
            <div id="swagger-ui"></div>
            <script src="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5/swagger-ui-bundle.js"></script>
            <script>
                window.onload = function() {
                    SwaggerUIBundle({
                        url: '<?php echo esc_url($specUrl); ?>',
                        dom_id: '#swagger-ui',
                        deepLinking: true,
                        presets: [
                            SwaggerUIBundle.presets.apis,
                            SwaggerUIBundle.SwaggerUIStandalonePreset
                        ],
                        plugins: [
                            SwaggerUIBundle.plugins.DownloadUrl
                        ],
                        layout: "BaseLayout"
                    });
                };
            </script>
        </body>
        </html>
        <?php
    }
}
