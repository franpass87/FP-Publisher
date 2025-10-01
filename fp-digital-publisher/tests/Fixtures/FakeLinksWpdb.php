<?php

declare(strict_types=1);

namespace FP\Publisher\Tests\Fixtures;

final class FakeLinksWpdb extends \wpdb
{
    public string $prefix = 'wp_';
    public string $last_error = '';
    public int $insert_id = 0;
    public mixed $nextInsertResult = true;
    public mixed $nextUpdateResult = 1;

    /**
     * @var array<int, array<string, mixed>>
     */
    private array $results = [];

    /**
     * @var array<string, array<string, mixed>>
     */
    private array $rows = [];

    /**
     * @param array<int, array<string, mixed>> $results
     */
    public function setResults(array $results): void
    {
        $this->results = $results;
        $this->rows = [];

        foreach ($results as $row) {
            if (isset($row['slug'])) {
                $this->rows[(string) $row['slug']] = $row;
            }
        }
    }

    public function get_results(mixed $prepared, mixed $output = ARRAY_A): array
    {
        return $this->results;
    }

    public function get_row(mixed $prepared, mixed $output = ARRAY_A): ?array
    {
        if (! is_array($prepared) || ! isset($prepared['sql'], $prepared['args'])) {
            return null;
        }

        $sql = (string) $prepared['sql'];
        $args = $prepared['args'];

        if (str_contains($sql, 'WHERE slug = %s')) {
            $slug = (string) ($args[0] ?? '');

            return $this->rows[$slug] ?? null;
        }

        if (str_contains($sql, 'WHERE id = %d')) {
            $id = (int) ($args[0] ?? 0);
            foreach ($this->rows as $row) {
                if ((int) ($row['id'] ?? 0) === $id) {
                    return $row;
                }
            }
        }

        return null;
    }

    public function update(string $table, array $data, array $where, array $format = [], array $whereFormat = []): int|false
    {
        if ($this->nextUpdateResult === false) {
            $this->last_error = 'Forced update failure';

            return false;
        }

        if ($this->nextUpdateResult === 0) {
            $this->nextUpdateResult = 1;

            return 0;
        }

        $slug = $this->rowsByWhere($where)['slug'] ?? null;
        if ($slug !== null && isset($this->rows[$slug])) {
            $this->rows[$slug] = array_merge($this->rows[$slug], $data);
        }

        return 1;
    }

    public function insert(string $table, array $data, array $format = []): bool
    {
        if ($this->nextInsertResult === false) {
            $this->last_error = 'Forced insert failure';

            return false;
        }

        $this->insert_id++;
        $data['id'] = $this->insert_id;
        $slug = (string) ($data['slug'] ?? '');
        if ($slug !== '') {
            $this->rows[$slug] = $data;
        }

        return true;
    }

    public function prepare(string $query, mixed ...$args): array
    {
        return [
            'sql' => $query,
            'args' => $args,
        ];
    }

    public function get_var(mixed $prepared): int
    {
        return count($this->results);
    }

    /**
     * @param array<string, mixed> $where
     * @return array<string, mixed>
     */
    private function rowsByWhere(array $where): array
    {
        if (isset($where['id'])) {
            $id = (int) $where['id'];
            foreach ($this->rows as $row) {
                if ((int) ($row['id'] ?? 0) === $id) {
                    return $row;
                }
            }
        }

        if (isset($where['slug'])) {
            $slug = (string) $where['slug'];

            return $this->rows[$slug] ?? [];
        }

        return [];
    }
}
