<?php

declare(strict_types=1);

namespace FP\Publisher\Tests\Fixtures;

final class FakeAlertsWpdb extends \wpdb
{
    public string $prefix = 'wp_';

    /** @var array<int, array<string, mixed>> */
    public array $tokenRows = [];

    /** @var array<int, array<string, mixed>> */
    public array $failedJobRows = [];

    /** @var array<int, array<string, mixed>> */
    public array $planRows = [];

    public function prepare(string $query, mixed ...$args): array
    {
        return [
            'sql' => $query,
            'args' => $args,
        ];
    }

    public function get_results(mixed $prepared, mixed $output = ARRAY_A): array
    {
        $sql = is_array($prepared) ? (string) ($prepared['sql'] ?? '') : (string) $prepared;

        if (str_contains($sql, 'fp_pub_tokens')) {
            return $this->tokenRows;
        }

        if (str_contains($sql, 'fp_pub_jobs')) {
            return $this->failedJobRows;
        }

        if (str_contains($sql, 'fp_pub_plans')) {
            return $this->planRows;
        }

        return [];
    }
}
