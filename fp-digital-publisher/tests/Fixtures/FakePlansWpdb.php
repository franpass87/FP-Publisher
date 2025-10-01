<?php

declare(strict_types=1);

namespace FP\Publisher\Tests\Fixtures;

use FP\Publisher\Support\Channels;

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

        if (is_string($sql) && str_contains($sql, 'fp_pub_plans')) {
            return $this->filterPlans($query, true);
        }

        return array_values($this->plans);
    }

    public function get_var($query, $x = null, $y = null)
    {
        $normalized = $this->normalizeQuery($query);
        if ($normalized === null) {
            return null;
        }

        $sql = $normalized['sql'];
        if (is_string($sql) && str_contains($sql, 'COUNT(*)') && str_contains($sql, 'fp_pub_plans')) {
            return count($this->filterPlans($normalized, false));
        }

        return null;
    }

    public function esc_like($text)
    {
        return addcslashes((string) $text, "\\_%");
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

    /**
     * @param array{sql:string,args:array<int,mixed>} $query
     * @return array<int, array<string, mixed>>
     */
    private function filterPlans(array $query, bool $applyLimit): array
    {
        $sql = $query['sql'];
        $args = $query['args'];

        $brand = null;
        $channel = null;
        $month = null;
        $index = 0;

        if (is_string($sql) && str_contains($sql, 'brand = %s')) {
            $value = (string) ($args[$index++] ?? '');
            $brand = $value !== '' ? $value : null;
        }

        if (is_string($sql) && str_contains($sql, 'channel_set_json LIKE %s')) {
            $pattern = (string) ($args[$index++] ?? '');
            if ($pattern !== '') {
                if (preg_match('/"([^"]+)"/', $pattern, $matches) === 1) {
                    $channel = $matches[1];
                } else {
                    $channel = trim($pattern, '%');
                }
            }
        }

        if (is_string($sql) && str_contains($sql, 'slots_json LIKE %s')) {
            $pattern = (string) ($args[$index++] ?? '');
            if (preg_match('/"scheduled_at":"(\d{4}-\d{2})/', $pattern, $matches) === 1) {
                $month = $matches[1];
            } elseif ($pattern !== '' && preg_match('/(\d{4}-\d{2})/', $pattern, $matches) === 1) {
                $month = $matches[1];
            }
        }

        $limit = null;
        $offset = 0;
        if ($applyLimit && is_string($sql)) {
            if (str_contains($sql, 'LIMIT %d OFFSET %d')) {
                $limit = (int) ($args[$index++] ?? 0);
                $offset = (int) ($args[$index++] ?? 0);
            } elseif (str_contains($sql, 'LIMIT %d')) {
                $limit = (int) ($args[$index++] ?? 0);
            }
        }

        $plans = array_values($this->plans);

        $filtered = array_values(array_filter($plans, function (array $plan) use ($brand, $channel, $month): bool {
            if ($brand !== null && strcasecmp((string) ($plan['brand'] ?? ''), $brand) !== 0) {
                return false;
            }

            if ($channel !== null && ! $this->planRowHasChannel($plan, $channel)) {
                return false;
            }

            if ($month !== null && ! $this->planRowHasMonth($plan, $month)) {
                return false;
            }

            return true;
        }));

        usort(
            $filtered,
            static function (array $left, array $right): int {
                $leftCreated = (string) ($left['created_at'] ?? '');
                $rightCreated = (string) ($right['created_at'] ?? '');

                if ($leftCreated === $rightCreated) {
                    return (int) ($right['id'] ?? 0) <=> (int) ($left['id'] ?? 0);
                }

                return strcmp($rightCreated, $leftCreated);
            }
        );

        if ($applyLimit && $limit !== null) {
            $filtered = array_slice($filtered, $offset, $limit);
        }

        return $filtered;
    }

    private function planRowHasChannel(array $plan, string $channel): bool
    {
        $raw = json_decode((string) ($plan['channel_set_json'] ?? '[]'), true);
        if (! is_array($raw)) {
            return false;
        }

        foreach ($raw as $value) {
            if (! is_string($value)) {
                continue;
            }

            if (Channels::normalize($value) === Channels::normalize($channel)) {
                return true;
            }
        }

        return false;
    }

    private function planRowHasMonth(array $plan, string $month): bool
    {
        $slots = json_decode((string) ($plan['slots_json'] ?? '[]'), true);
        if (! is_array($slots)) {
            return false;
        }

        foreach ($slots as $slot) {
            if (! is_array($slot)) {
                continue;
            }

            $scheduled = isset($slot['scheduled_at']) ? (string) $slot['scheduled_at'] : '';
            if ($scheduled !== '' && str_starts_with($scheduled, $month)) {
                return true;
            }
        }

        return false;
    }
}
