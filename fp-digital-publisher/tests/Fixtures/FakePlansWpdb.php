<?php

declare(strict_types=1);

namespace FP\Publisher\Tests\Fixtures;

class FakePlansWpdb extends \wpdb
{
    public string $prefix = 'wp_';
    public string $last_error = '';

    /**
     * @var array<int, array<string, mixed>>
     */
    private array $plans = [];

    /**
     * @var array<int, array<string, mixed>>
     */
    private array $assets = [];

    public function prepare(string $query, mixed ...$args): array
    {
        return [
            'sql' => $query,
            'args' => $args,
        ];
    }

    public function get_row(mixed $prepared, mixed $output = ARRAY_A): ?array
    {
        $query = $this->normalizeQuery($prepared);
        if ($query === null) {
            return null;
        }

        $id = (int) ($query['args'][0] ?? 0);

        return $this->plans[$id] ?? null;
    }

    public function get_results(mixed $prepared, mixed $output = ARRAY_A): array
    {
        $query = $this->normalizeQuery($prepared);
        if ($query === null) {
            return [];
        }

        $sql = $query['sql'];
        if (is_string($sql) && str_contains($sql, 'fp_pub_assets')) {
            return array_values($this->assets);
        }

        return array_values($this->plans);
    }

    public function update(string $table, array $data, array $where, array $format = [], array $whereFormat = []): int|false
    {
        if (! str_ends_with($table, 'fp_pub_plans')) {
            $this->last_error = 'Unknown table ' . $table;

            return false;
        }

        $id = (int) ($where['id'] ?? 0);
        if (! isset($this->plans[$id])) {
            return 0;
        }

        foreach ($data as $key => $value) {
            $this->plans[$id][$key] = $value;
        }

        return 1;
    }

    public function query($query)
    {
        $normalized = $this->normalizeQuery($query);
        if ($normalized === null) {
            return false;
        }

        $sql = $normalized['sql'];
        if (! is_string($sql)) {
            return false;
        }

        if (str_contains($sql, 'DELETE FROM ' . $this->prefix . 'fp_pub_assets')) {
            if (preg_match('/IN \(([^)]+)\)/', $sql, $matches) === 1) {
                $ids = array_map('intval', explode(',', $matches[1]));
                foreach ($ids as $id) {
                    unset($this->assets[$id]);
                }
            } else {
                $this->assets = [];
            }

            return 1;
        }

        if (str_contains($sql, 'DELETE FROM ' . $this->prefix . 'fp_pub_plans')) {
            if (preg_match('/IN \(([^)]+)\)/', $sql, $matches) === 1) {
                $ids = array_map('intval', explode(',', $matches[1]));
                foreach ($ids as $id) {
                    unset($this->plans[$id]);
                }
            }

            return 1;
        }

        return 0;
    }

    public function table(): string
    {
        return $this->prefix . 'fp_pub_plans';
    }

    /**
     * @param array<string, mixed> $plan
     */
    public function setPlan(array $plan): void
    {
        if (! isset($plan['id'])) {
            throw new \InvalidArgumentException('Missing plan id.');
        }

        $this->plans[(int) $plan['id']] = $plan;
    }

    /**
     * @param array<string, mixed> $asset
     */
    public function setAsset(array $asset): void
    {
        if (! isset($asset['id'])) {
            throw new \InvalidArgumentException('Missing asset id.');
        }

        $this->assets[(int) $asset['id']] = $asset;
    }

    /**
     * @return array{sql:string,args:array<int,mixed>}|null
     */
    private function normalizeQuery(mixed $prepared): ?array
    {
        if (is_array($prepared) && isset($prepared['sql'], $prepared['args'])) {
            return $prepared;
        }

        if (is_string($prepared)) {
            return ['sql' => $prepared, 'args' => []];
        }

        return null;
    }
}
