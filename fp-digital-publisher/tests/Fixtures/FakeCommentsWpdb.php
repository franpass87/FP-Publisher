<?php

declare(strict_types=1);

namespace FP\Publisher\Tests\Fixtures;

final class FakeCommentsWpdb extends \wpdb
{
    public string $prefix = 'wp_';
    public string $users = 'wp_users';
    public int $insert_id = 0;
    public string $last_error = '';
    public bool $forceInsertFailure = false;

    /**
     * @var array<int, array<string, mixed>>
     */
    private array $listResults = [];

    /**
     * @var array<int, array<string, mixed>>
     */
    public array $insertedRows = [];

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    public function setListResults(array $rows): void
    {
        $this->listResults = $rows;
    }

    public function get_results(mixed $prepared, mixed $output = ARRAY_A): array
    {
        return $this->listResults;
    }

    public function prepare(string $query, mixed ...$args): array
    {
        return [
            'sql' => $query,
            'args' => $args,
        ];
    }

    public function insert(string $table, array $data, array $format = []): bool
    {
        if (! str_ends_with($table, 'fp_pub_comments')) {
            $this->last_error = 'Unknown table ' . $table;

            return false;
        }

        if ($this->forceInsertFailure) {
            $this->last_error = 'Forced failure';

            return false;
        }

        $this->insert_id++;
        $data['id'] = $this->insert_id;
        $this->insertedRows[] = $data;

        return true;
    }
}
