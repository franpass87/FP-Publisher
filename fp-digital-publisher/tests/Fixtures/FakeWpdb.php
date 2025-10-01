<?php

declare(strict_types=1);

namespace FP\Publisher\Tests\Fixtures;

use DateTimeImmutable;
use RuntimeException;

final class FakeWpdb extends \wpdb
{
    public string $prefix = 'wp_';
    public int $insert_id = 0;
    public string $last_error = '';
    public int|false|null $nextUpdateResult = null;
    public bool|null $nextInsertResult = null;
    public string $nextInsertError = '';

    /**
     * @var array<int, array<string, mixed>>
     */
    private array $jobs = [];

    /**
     * @param array<string, mixed> $data
     */
    public function insert(string $table, array $data): bool
    {
        if ($this->nextInsertResult !== null) {
            $forced = $this->nextInsertResult;
            $this->nextInsertResult = null;
            $this->last_error = $this->nextInsertError;
            $this->nextInsertError = '';

            if ($forced === false) {
                return false;
            }
        }

        if (! str_ends_with($table, 'fp_pub_jobs')) {
            $this->last_error = 'Unknown table ' . $table;

            return false;
        }

        $this->insert_id++;
        $id = $this->insert_id;
        $data['id'] = $id;
        $this->jobs[$id] = $data;

        return true;
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $where
     */
    public function update(string $table, array $data, array $where, array $format = [], array $whereFormat = []): int|false
    {
        if ($this->nextUpdateResult !== null) {
            $forced = $this->nextUpdateResult;
            $this->nextUpdateResult = null;

            if ($forced === false) {
                $this->last_error = 'Forced update failure';

                return false;
            }

            if ($forced <= 0) {
                return $forced;
            }

            $resultOverride = $forced;
        } else {
            $resultOverride = null;
        }

        if (! str_ends_with($table, 'fp_pub_jobs')) {
            $this->last_error = 'Unknown table ' . $table;

            return false;
        }

        $matched = 0;
        foreach ($this->jobs as $id => $row) {
            if (! $this->matchesWhere($row, $where)) {
                continue;
            }

            $matched++;
            foreach ($data as $key => $value) {
                $this->jobs[$id][$key] = $value;
            }
        }

        if ($resultOverride !== null) {
            return $resultOverride;
        }

        return $matched;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get_row(mixed $prepared, mixed $output = ARRAY_A): ?array
    {
        $query = $this->normalizeQuery($prepared);
        if ($query === null) {
            return null;
        }

        if (str_contains($query['sql'], 'WHERE id = %d')) {
            $id = (int) ($query['args'][0] ?? 0);
            $row = $this->jobs[$id] ?? null;

            return $row !== null ? $this->normalizeRow($row) : null;
        }

        if (str_contains($query['sql'], 'WHERE idempotency_key = %s')) {
            $key = (string) ($query['args'][0] ?? '');
            $channel = null;

            if (str_contains($query['sql'], 'AND channel = %s')) {
                $channel = (string) ($query['args'][1] ?? '');
            }

            foreach ($this->jobs as $row) {
                if (($row['idempotency_key'] ?? '') !== $key) {
                    continue;
                }

                if ($channel !== null && $channel !== '' && ($row['channel'] ?? '') !== $channel) {
                    continue;
                }

                return $this->normalizeRow($row);
            }
        }

        return null;
    }

    public mixed $nextGetResults = null;
    public mixed $nextGetVar = null;

    /**
     * @return array<int, array<string, mixed>>|null
     */
    public function get_results(mixed $prepared, mixed $output = ARRAY_A): ?array
    {
        if ($this->nextGetResults !== null) {
            $forced = $this->nextGetResults;
            $this->nextGetResults = null;

            return is_array($forced) ? $forced : null;
        }

        $query = $this->normalizeQuery($prepared);
        if ($query === null) {
            return [];
        }

        if (str_contains($query['sql'], 'WHERE status = %s AND run_at <= %s')) {
            $status = (string) ($query['args'][0] ?? '');
            $runAt = (string) ($query['args'][1] ?? '');
            $limit = (int) ($query['args'][2] ?? 0);

            $rows = array_filter(
                $this->jobs,
                static fn (array $row): bool => ($row['status'] ?? '') === $status && ($row['run_at'] ?? '') <= $runAt
            );

            usort(
                $rows,
                static function (array $a, array $b): int {
                    $runAtComparison = strcmp((string) ($a['run_at'] ?? ''), (string) ($b['run_at'] ?? ''));
                    if ($runAtComparison !== 0) {
                        return $runAtComparison;
                    }

                    return ((int) ($a['id'] ?? 0)) <=> ((int) ($b['id'] ?? 0));
                }
            );

            if ($limit > 0) {
                $rows = array_slice($rows, 0, $limit);
            }

            return array_map(fn (array $row): array => $this->normalizeRow($row), $rows);
        }

        if (str_contains($query['sql'], 'WHERE status = %s GROUP BY channel')) {
            $status = (string) ($query['args'][0] ?? '');
            $grouped = [];
            foreach ($this->jobs as $row) {
                if (($row['status'] ?? '') !== $status) {
                    continue;
                }

                $channel = (string) ($row['channel'] ?? '');
                $grouped[$channel] = ($grouped[$channel] ?? 0) + 1;
            }

            $results = [];
            foreach ($grouped as $channel => $count) {
                $results[] = ['channel' => $channel, 'total' => $count];
            }

            return $results;
        }

        return [];
    }

    public function get_var(mixed $prepared, mixed $output = 0, mixed $y = 0): mixed
    {
        if ($this->nextGetVar !== null) {
            $forced = $this->nextGetVar;
            $this->nextGetVar = null;

            return $forced;
        }

        $query = $this->normalizeQuery($prepared);
        if ($query === null) {
            return null;
        }

        if (! str_contains($query['sql'], 'COUNT(*)')) {
            return null;
        }

        $rows = $this->jobs;
        $args = $query['args'];

        if (str_contains($query['sql'], 'status = %s') && $args !== []) {
            $status = (string) array_shift($args);
            $rows = array_filter($rows, static fn (array $row): bool => ($row['status'] ?? '') === $status);
        }

        if (str_contains($query['sql'], 'channel = %s') && $args !== []) {
            $channel = (string) array_shift($args);
            $rows = array_filter(
                $rows,
                static function (array $row) use ($channel): bool {
                    return ($row['channel'] ?? '') === $channel;
                }
            );
        }

        if (str_contains($query['sql'], 'idempotency_key LIKE %s') && $args !== []) {
            $like = (string) array_shift($args);
            // Skip OR error LIKE %s duplicate argument.
            if ($args !== []) {
                array_shift($args);
            }
            $pattern = '/^' . str_replace('%', '.*', preg_quote($like, '/')) . '$/';
            $rows = array_filter(
                $rows,
                static fn (array $row): bool => isset($row['idempotency_key']) && $row['idempotency_key'] !== ''
                    && preg_match($pattern, (string) $row['idempotency_key']) === 1
                    || (isset($row['error']) && preg_match($pattern, (string) $row['error']) === 1)
            );
        }

        return count($rows);
    }

    public function prepare(string $query, mixed ...$args): array
    {
        return [
            'sql' => $query,
            'args' => $args,
        ];
    }

    public function table(): string
    {
        return $this->prefix . 'fp_pub_jobs';
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findJob(int $id): ?array
    {
        $row = $this->jobs[$id] ?? null;

        return $row !== null ? $this->normalizeRow($row) : null;
    }

    /**
     * @param array<string, mixed> $row
     */
    public function setJob(array $row): void
    {
        if (! isset($row['id'])) {
            throw new RuntimeException('Job id missing.');
        }

        $id = (int) $row['id'];
        $this->jobs[$id] = $row;
    }

    public function reset(): void
    {
        $this->jobs = [];
        $this->insert_id = 0;
        $this->last_error = '';
        $this->nextUpdateResult = null;
        $this->nextGetResults = null;
        $this->nextGetVar = null;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function normalizeRow(array $row): array
    {
        $row['id'] = (int) ($row['id'] ?? 0);
        $row['attempts'] = (int) ($row['attempts'] ?? 0);
        $row['payload_json'] = (string) ($row['payload_json'] ?? '[]');
        $row['run_at'] = (string) ($row['run_at'] ?? (new DateTimeImmutable('now'))->format('Y-m-d H:i:s'));
        $row['created_at'] = (string) ($row['created_at'] ?? (new DateTimeImmutable('now'))->format('Y-m-d H:i:s'));
        $row['updated_at'] = (string) ($row['updated_at'] ?? (new DateTimeImmutable('now'))->format('Y-m-d H:i:s'));

        return $row;
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, mixed> $where
     */
    private function matchesWhere(array $row, array $where): bool
    {
        foreach ($where as $key => $value) {
            if (($row[$key] ?? null) !== $value) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array{sql:string,args:array<int,mixed>}|null
     */
    private function normalizeQuery(mixed $prepared): ?array
    {
        if (is_array($prepared) && isset($prepared['sql'], $prepared['args'])) {
            return [
                'sql' => (string) $prepared['sql'],
                'args' => $prepared['args'],
            ];
        }

        if (is_string($prepared)) {
            return ['sql' => $prepared, 'args' => []];
        }

        return null;
    }
}
