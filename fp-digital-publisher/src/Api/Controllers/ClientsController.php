<?php

declare(strict_types=1);

namespace FP\Publisher\Api\Controllers;

use FP\Publisher\Services\ClientService;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

use function current_user_can;
use function get_current_user_id;
use function is_wp_error;
use function rest_ensure_response;

final class ClientsController extends BaseController
{
    public static function registerRoutes(): void
    {
        // List clients for current user
        register_rest_route(
            'fp-publisher/v1',
            '/clients',
            [
                'methods' => 'GET',
                'callback' => [self::class, 'listClients'],
                'permission_callback' => [self::class, 'checkManagePermission'],
            ]
        );

        // Create client
        register_rest_route(
            'fp-publisher/v1',
            '/clients',
            [
                'methods' => 'POST',
                'callback' => [self::class, 'createClient'],
                'permission_callback' => [self::class, 'checkManagePermission'],
            ]
        );

        // Get single client
        register_rest_route(
            'fp-publisher/v1',
            '/clients/(?P<id>\d+)',
            [
                'methods' => 'GET',
                'callback' => [self::class, 'getClient'],
                'permission_callback' => [self::class, 'checkClientAccess'],
            ]
        );

        // Update client
        register_rest_route(
            'fp-publisher/v1',
            '/clients/(?P<id>\d+)',
            [
                'methods' => 'PUT',
                'callback' => [self::class, 'updateClient'],
                'permission_callback' => [self::class, 'checkClientOwner'],
            ]
        );

        // Delete client
        register_rest_route(
            'fp-publisher/v1',
            '/clients/(?P<id>\d+)',
            [
                'methods' => 'DELETE',
                'callback' => [self::class, 'deleteClient'],
                'permission_callback' => [self::class, 'checkClientOwner'],
            ]
        );

        // Client accounts
        register_rest_route(
            'fp-publisher/v1',
            '/clients/(?P<id>\d+)/accounts',
            [
                'methods' => 'GET',
                'callback' => [self::class, 'listAccounts'],
                'permission_callback' => [self::class, 'checkClientAccess'],
            ]
        );

        // Connect account
        register_rest_route(
            'fp-publisher/v1',
            '/clients/(?P<id>\d+)/accounts',
            [
                'methods' => 'POST',
                'callback' => [self::class, 'connectAccount'],
                'permission_callback' => [self::class, 'checkClientAdmin'],
            ]
        );

        // Disconnect account
        register_rest_route(
            'fp-publisher/v1',
            '/clients/(?P<client_id>\d+)/accounts/(?P<account_id>\d+)',
            [
                'methods' => 'DELETE',
                'callback' => [self::class, 'disconnectAccount'],
                'permission_callback' => [self::class, 'checkClientAdmin'],
            ]
        );

        // Client members
        register_rest_route(
            'fp-publisher/v1',
            '/clients/(?P<id>\d+)/members',
            [
                'methods' => 'GET',
                'callback' => [self::class, 'listMembers'],
                'permission_callback' => [self::class, 'checkClientAccess'],
            ]
        );

        // Add member
        register_rest_route(
            'fp-publisher/v1',
            '/clients/(?P<id>\d+)/members',
            [
                'methods' => 'POST',
                'callback' => [self::class, 'addMember'],
                'permission_callback' => [self::class, 'checkClientAdmin'],
            ]
        );

        // Remove member
        register_rest_route(
            'fp-publisher/v1',
            '/clients/(?P<client_id>\d+)/members/(?P<user_id>\d+)',
            [
                'methods' => 'DELETE',
                'callback' => [self::class, 'removeMember'],
                'permission_callback' => [self::class, 'checkClientAdmin'],
            ]
        );
    }

    public static function listClients(WP_REST_Request $request): WP_REST_Response
    {
        $userId = get_current_user_id();
        $filters = [];

        if ($request->get_param('status')) {
            $filters['status'] = $request->get_param('status');
        }

        $clients = ClientService::listForUser($userId, $filters);
        $data = array_map(fn ($client) => $client->toArray(), $clients);

        return rest_ensure_response(['clients' => $data, 'total' => count($data)]);
    }

    public static function createClient(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $data = $request->get_json_params();

        try {
            $client = ClientService::create($data);

            return rest_ensure_response([
                'success' => true,
                'client' => $client->toArray(),
            ]);
        } catch (\Throwable $e) {
            return new WP_Error(
                'client_create_failed',
                $e->getMessage(),
                ['status' => 400]
            );
        }
    }

    public static function getClient(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $id = (int) $request->get_param('id');
        $client = ClientService::findById($id);

        if ($client === null) {
            return new WP_Error('client_not_found', 'Client not found', ['status' => 404]);
        }

        return rest_ensure_response($client->toArray());
    }

    public static function updateClient(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $id = (int) $request->get_param('id');
        $data = $request->get_json_params();

        try {
            $updated = ClientService::update($id, $data);

            if (! $updated) {
                return new WP_Error('client_update_failed', 'Failed to update client', ['status' => 500]);
            }

            $client = ClientService::findById($id);

            return rest_ensure_response([
                'success' => true,
                'client' => $client?->toArray(),
            ]);
        } catch (\Throwable $e) {
            return new WP_Error('client_update_error', $e->getMessage(), ['status' => 400]);
        }
    }

    public static function deleteClient(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $id = (int) $request->get_param('id');

        $deleted = ClientService::delete($id);

        if (! $deleted) {
            return new WP_Error('client_delete_failed', 'Failed to delete client', ['status' => 500]);
        }

        return rest_ensure_response(['success' => true]);
    }

    public static function listAccounts(WP_REST_Request $request): WP_REST_Response
    {
        $clientId = (int) $request->get_param('id');
        $channel = $request->get_param('channel');

        $accounts = ClientService::getAccountsForClient($clientId, $channel);
        $data = array_map(fn ($account) => $account->toArray(), $accounts);

        return rest_ensure_response(['accounts' => $data, 'total' => count($data)]);
    }

    public static function connectAccount(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $clientId = (int) $request->get_param('id');
        $data = $request->get_json_params();

        try {
            $account = ClientService::connectAccount($clientId, $data);

            return rest_ensure_response([
                'success' => true,
                'account' => $account->toArray(),
            ]);
        } catch (\Throwable $e) {
            return new WP_Error('account_connect_failed', $e->getMessage(), ['status' => 400]);
        }
    }

    public static function disconnectAccount(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $accountId = (int) $request->get_param('account_id');

        $deleted = ClientService::disconnectAccount($accountId);

        if (! $deleted) {
            return new WP_Error('account_disconnect_failed', 'Failed to disconnect account', ['status' => 500]);
        }

        return rest_ensure_response(['success' => true]);
    }

    public static function listMembers(WP_REST_Request $request): WP_REST_Response
    {
        $clientId = (int) $request->get_param('id');
        $members = ClientService::getMembersForClient($clientId);
        $data = array_map(fn ($member) => $member->toArray(), $members);

        return rest_ensure_response(['members' => $data, 'total' => count($data)]);
    }

    public static function addMember(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $clientId = (int) $request->get_param('id');
        $data = $request->get_json_params();
        $userId = (int) ($data['user_id'] ?? 0);
        $role = (string) ($data['role'] ?? 'viewer');
        $invitedBy = get_current_user_id();

        try {
            $member = ClientService::addMember($clientId, $userId, $role, $invitedBy);

            return rest_ensure_response([
                'success' => true,
                'member' => $member->toArray(),
            ]);
        } catch (\Throwable $e) {
            return new WP_Error('member_add_failed', $e->getMessage(), ['status' => 400]);
        }
    }

    public static function removeMember(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $clientId = (int) $request->get_param('client_id');
        $userId = (int) $request->get_param('user_id');

        $deleted = ClientService::removeMember($clientId, $userId);

        if (! $deleted) {
            return new WP_Error('member_remove_failed', 'Failed to remove member', ['status' => 500]);
        }

        return rest_ensure_response(['success' => true]);
    }

    public static function checkManagePermission(): bool
    {
        return current_user_can('fp_publisher_manage_plans');
    }

    public static function checkClientAccess(WP_REST_Request $request): bool
    {
        if (! current_user_can('fp_publisher_manage_plans')) {
            return false;
        }

        $clientId = (int) $request->get_param('id');
        $userId = get_current_user_id();

        // Check if user is member of this client
        $member = ClientService::getMemberForUser($clientId, $userId);

        return $member !== null && $member->isActive();
    }

    public static function checkClientAdmin(WP_REST_Request $request): bool
    {
        if (! current_user_can('fp_publisher_manage_plans')) {
            return false;
        }

        $clientId = (int) ($request->get_param('id') ?? $request->get_param('client_id'));
        $userId = get_current_user_id();

        $member = ClientService::getMemberForUser($clientId, $userId);

        return $member !== null && $member->isActive() && $member->canManageTeam();
    }

    public static function checkClientOwner(WP_REST_Request $request): bool
    {
        if (! current_user_can('fp_publisher_manage_plans')) {
            return false;
        }

        $clientId = (int) $request->get_param('id');
        $userId = get_current_user_id();

        $member = ClientService::getMemberForUser($clientId, $userId);

        return $member !== null && $member->isActive() && $member->isOwner();
    }
}
