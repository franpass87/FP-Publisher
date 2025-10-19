<?php

declare(strict_types=1);

namespace FP\Publisher\Services;

use FP\Publisher\Domain\Client;
use FP\Publisher\Domain\ClientAccount;
use FP\Publisher\Domain\ClientMember;
use FP\Publisher\Support\Dates;
use FP\Publisher\Support\Logging\Logger;
use RuntimeException;
use wpdb;

use function array_map;
use function get_current_user_id;
use function is_array;
use function json_decode;
use function wp_json_encode;

final class ClientService
{
    /**
     * @param array<string, mixed> $data
     */
    public static function create(array $data): Client
    {
        global $wpdb;

        $client = Client::create($data);
        $now = Dates::now('UTC')->format('Y-m-d H:i:s');

        $inserted = $wpdb->insert(
            self::table(),
            [
                'name' => $client->name(),
                'slug' => $client->slug(),
                'logo_url' => $client->logoUrl(),
                'website' => $client->website(),
                'industry' => $client->industry(),
                'timezone' => $client->timezone(),
                'color' => $client->color(),
                'status' => $client->status(),
                'meta' => wp_json_encode($client->meta()),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
        );

        if ($inserted === false) {
            throw new RuntimeException('Failed to create client: ' . $wpdb->last_error);
        }

        $clientId = (int) $wpdb->insert_id;

        // Add current user as owner
        $currentUserId = get_current_user_id();
        if ($currentUserId > 0) {
            self::addMember($clientId, $currentUserId, ClientMember::ROLE_OWNER, $currentUserId);
        }

        Logger::get()->info('Client created', [
            'client_id' => $clientId,
            'name' => $client->name(),
        ]);

        return self::findById($clientId) ?? $client;
    }

    public static function findById(int $id): ?Client
    {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM " . self::table() . " WHERE id = %d", $id),
            ARRAY_A
        );

        if (! $row || ! is_array($row)) {
            return null;
        }

        return self::hydrateClient($row);
    }

    public static function findBySlug(string $slug): ?Client
    {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM " . self::table() . " WHERE slug = %s", $slug),
            ARRAY_A
        );

        if (! $row || ! is_array($row)) {
            return null;
        }

        return self::hydrateClient($row);
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<int, Client>
     */
    public static function listForUser(int $userId, array $filters = []): array
    {
        global $wpdb;

        $query = "
            SELECT DISTINCT c.*
            FROM " . self::table() . " c
            INNER JOIN " . self::membersTable() . " m ON c.id = m.client_id
            WHERE m.user_id = %d AND m.status = 'active'
        ";

        $params = [$userId];

        if (! empty($filters['status'])) {
            $query .= " AND c.status = %s";
            $params[] = $filters['status'];
        }

        if (! empty($filters['billing_plan'])) {
            $query .= " AND c.billing_plan = %s";
            $params[] = $filters['billing_plan'];
        }

        $query .= " ORDER BY c.name ASC";

        $rows = $wpdb->get_results($wpdb->prepare($query, ...$params), ARRAY_A);

        if (! is_array($rows)) {
            return [];
        }

        return array_map(fn ($row) => self::hydrateClient($row), $rows);
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function update(int $id, array $data): bool
    {
        global $wpdb;

        $data['updated_at'] = Dates::now('UTC')->format('Y-m-d H:i:s');

        if (isset($data['meta']) && is_array($data['meta'])) {
            $data['meta'] = wp_json_encode($data['meta']);
        }

        // Filter out unsupported columns (e.g. billing_plan) to avoid SQL errors
        $allowedColumns = [
            'name', 'slug', 'logo_url', 'website', 'industry', 'timezone',
            'color', 'status', 'meta', 'updated_at',
        ];
        $filtered = array_intersect_key($data, array_flip($allowedColumns));

        if ($filtered === []) {
            return true; // nothing to update
        }

        $updated = $wpdb->update(
            self::table(),
            $filtered,
            ['id' => $id],
            null,
            ['%d']
        );

        Logger::get()->info('Client updated', ['client_id' => $id]);

        return $updated !== false;
    }

    public static function delete(int $id): bool
    {
        global $wpdb;

        // Delete related records (cascade)
        $wpdb->delete(self::accountsTable(), ['client_id' => $id], ['%d']);
        $wpdb->delete(self::membersTable(), ['client_id' => $id], ['%d']);
        $wpdb->delete($wpdb->prefix . 'fp_plans', ['client_id' => $id], ['%d']);

        $deleted = $wpdb->delete(self::table(), ['id' => $id], ['%d']);

        Logger::get()->info('Client deleted', ['client_id' => $id]);

        return $deleted !== false;
    }

    /**
     * @param array<string, mixed> $accountData
     */
    public static function connectAccount(int $clientId, array $accountData): ClientAccount
    {
        global $wpdb;

        $account = ClientAccount::create(array_merge($accountData, ['client_id' => $clientId]));

        $wpdb->insert(
            self::accountsTable(),
            [
                'client_id' => $clientId,
                'channel' => $account->channel(),
                'account_identifier' => $account->accountIdentifier(),
                'account_name' => $account->accountName(),
                'account_avatar' => $account->accountAvatar(),
                'status' => $account->status(),
                'connected_at' => $account->connectedAt()->format('Y-m-d H:i:s'),
                'last_synced_at' => $account->lastSyncedAt()?->format('Y-m-d H:i:s'),
                'tokens' => wp_json_encode($account->tokens()),
                'meta' => wp_json_encode($account->meta()),
            ],
            ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
        );

        $accountId = (int) $wpdb->insert_id;

        Logger::get()->info('Social account connected', [
            'client_id' => $clientId,
            'account_id' => $accountId,
            'channel' => $account->channel(),
        ]);

        return self::findAccountById($accountId) ?? $account;
    }

    public static function findAccountById(int $id): ?ClientAccount
    {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM " . self::accountsTable() . " WHERE id = %d", $id),
            ARRAY_A
        );

        if (! $row || ! is_array($row)) {
            return null;
        }

        return self::hydrateAccount($row);
    }

    /**
     * @return array<int, ClientAccount>
     */
    public static function getAccountsForClient(int $clientId, ?string $channel = null): array
    {
        global $wpdb;

        $query = "SELECT * FROM " . self::accountsTable() . " WHERE client_id = %d";
        $params = [$clientId];

        if ($channel !== null) {
            $query .= " AND channel = %s";
            $params[] = $channel;
        }

        $query .= " ORDER BY channel ASC, account_name ASC";

        $rows = $wpdb->get_results($wpdb->prepare($query, ...$params), ARRAY_A);

        if (! is_array($rows)) {
            return [];
        }

        return array_map(fn ($row) => self::hydrateAccount($row), $rows);
    }

    public static function disconnectAccount(int $accountId): bool
    {
        global $wpdb;

        $deleted = $wpdb->delete(self::accountsTable(), ['id' => $accountId], ['%d']);

        Logger::get()->info('Social account disconnected', ['account_id' => $accountId]);

        return $deleted !== false;
    }

    public static function addMember(
        int $clientId,
        int $userId,
        string $role,
        ?int $invitedBy = null
    ): ClientMember {
        global $wpdb;

        $now = Dates::now('UTC')->format('Y-m-d H:i:s');
        $member = ClientMember::create([
            'client_id' => $clientId,
            'user_id' => $userId,
            'role' => $role,
            'invited_by' => $invitedBy,
            'invited_at' => $now,
            'accepted_at' => $now,
            'status' => ClientMember::STATUS_ACTIVE,
        ]);

        $wpdb->insert(
            self::membersTable(),
            [
                'client_id' => $clientId,
                'user_id' => $userId,
                'role' => $role,
                'invited_by' => $invitedBy,
                'invited_at' => $now,
                'accepted_at' => $now,
                'status' => ClientMember::STATUS_ACTIVE,
                'permissions' => '{}',
            ],
            ['%d', '%d', '%s', '%d', '%s', '%s', '%s', '%s']
        );

        Logger::get()->info('Team member added', [
            'client_id' => $clientId,
            'user_id' => $userId,
            'role' => $role,
        ]);

        return $member;
    }

    /**
     * @return array<int, ClientMember>
     */
    public static function getMembersForClient(int $clientId): array
    {
        global $wpdb;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM " . self::membersTable() . " WHERE client_id = %d ORDER BY role ASC, invited_at ASC",
                $clientId
            ),
            ARRAY_A
        );

        if (! is_array($rows)) {
            return [];
        }

        return array_map(fn ($row) => self::hydrateMember($row), $rows);
    }

    public static function getMemberForUser(int $clientId, int $userId): ?ClientMember
    {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM " . self::membersTable() . " WHERE client_id = %d AND user_id = %d",
                $clientId,
                $userId
            ),
            ARRAY_A
        );

        if (! $row || ! is_array($row)) {
            return null;
        }

        return self::hydrateMember($row);
    }

    public static function removeMember(int $clientId, int $userId): bool
    {
        global $wpdb;

        $deleted = $wpdb->delete(
            self::membersTable(),
            ['client_id' => $clientId, 'user_id' => $userId],
            ['%d', '%d']
        );

        Logger::get()->info('Team member removed', [
            'client_id' => $clientId,
            'user_id' => $userId,
        ]);

        return $deleted !== false;
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function hydrateClient(array $row): Client
    {
        if (isset($row['meta']) && is_string($row['meta'])) {
            $decoded = json_decode($row['meta'], true);
            $row['meta'] = is_array($decoded) ? $decoded : [];
        }

        return Client::create($row);
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function hydrateAccount(array $row): ClientAccount
    {
        if (isset($row['tokens']) && is_string($row['tokens'])) {
            $decoded = json_decode($row['tokens'], true);
            $row['tokens'] = is_array($decoded) ? $decoded : [];
        }

        if (isset($row['meta']) && is_string($row['meta'])) {
            $decoded = json_decode($row['meta'], true);
            $row['meta'] = is_array($decoded) ? $decoded : [];
        }

        return ClientAccount::create($row);
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function hydrateMember(array $row): ClientMember
    {
        if (isset($row['permissions']) && is_string($row['permissions'])) {
            $decoded = json_decode($row['permissions'], true);
            $row['permissions'] = is_array($decoded) ? $decoded : [];
        }

        return ClientMember::create($row);
    }

    private static function table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'fp_clients';
    }

    private static function accountsTable(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'fp_client_accounts';
    }

    private static function membersTable(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'fp_client_members';
    }
}
