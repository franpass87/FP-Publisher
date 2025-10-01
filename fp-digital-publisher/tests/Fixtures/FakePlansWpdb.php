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
