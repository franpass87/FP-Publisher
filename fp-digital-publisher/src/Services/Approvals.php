<?php

declare(strict_types=1);

namespace FP\Publisher\Services;

use DateTimeInterface;
use FP\Publisher\Domain\PostPlan;
use FP\Publisher\Infra\Capabilities;
use FP\Publisher\Support\Dates;
use FP\Publisher\Support\Validation;
use FP\Publisher\Services\Exceptions\PlanPermissionDenied;
use InvalidArgumentException;
use RuntimeException;
use wpdb;

use function array_key_exists;
use function get_current_user_id;
use function is_array;
use function json_decode;
use function is_string;
use function wp_json_encode;
use function __;

final class Approvals
{
    /**
     * @var array<string, string>
     */
    private const TRANSITIONS = [
        PostPlan::STATUS_DRAFT => PostPlan::STATUS_READY,
        PostPlan::STATUS_READY => PostPlan::STATUS_APPROVED,
        PostPlan::STATUS_APPROVED => PostPlan::STATUS_SCHEDULED,
    ];

    /**
     * @var array<string, string>
     */
    private const CAPABILITIES = [
        PostPlan::STATUS_READY => 'fp_publisher_manage_plans',
        PostPlan::STATUS_APPROVED => 'fp_publisher_approve_plans',
        PostPlan::STATUS_SCHEDULED => 'fp_publisher_schedule_plans',
    ];

    /**
     * @return array{id:int,status:string,brand:string,approvals:array<int,array<string,mixed>>,updated_at:string}
     */
    public static function transition(int $planId, string $targetStatus): array
    {
        global $wpdb;

        $targetStatus = Validation::enum($targetStatus, PostPlan::statuses(), 'plan.status');

        // Start transaction for data consistency
        $wpdb->query('START TRANSACTION');

        try {
            $table = $wpdb->prefix . 'fp_pub_plans';
            $row = $wpdb->get_row($wpdb->prepare("SELECT id, brand, status, approvals_json FROM {$table} WHERE id = %d FOR UPDATE", $planId), ARRAY_A);

            if (! is_array($row)) {
                throw new RuntimeException(__('Plan not found.', 'fp-publisher'));
            }

        $currentStatus = (string) $row['status'];
        if (! array_key_exists($currentStatus, self::TRANSITIONS)) {
            throw new InvalidArgumentException(__('The current status does not support the approval workflow.', 'fp-publisher'));
        }

        $expected = self::TRANSITIONS[$currentStatus];
        if ($expected !== $targetStatus) {
            throw new InvalidArgumentException(__('Transition not allowed for the selected plan.', 'fp-publisher'));
        }

        $capability = self::CAPABILITIES[$targetStatus] ?? 'fp_publisher_manage_plans';
        if (! Capabilities::userCan($capability)) {
            throw new PlanPermissionDenied(__('You do not have permission to change this plan status.', 'fp-publisher'));
        }

        $approvals = [];
        if ($row['approvals_json'] !== null && $row['approvals_json'] !== '') {
            $decoded = json_decode((string) $row['approvals_json'], true);
            if (is_array($decoded)) {
                $approvals = $decoded;
            }
        }

        $timestamp = Dates::now();
        $entry = [
            'user_id' => get_current_user_id(),
            'from' => $currentStatus,
            'to' => $targetStatus,
            'at' => $timestamp->format(DateTimeInterface::ATOM),
        ];
        $approvals[] = $entry;

        $encodedApprovals = wp_json_encode($approvals);
        if (! is_string($encodedApprovals)) {
            throw new RuntimeException(__('Unable to store the approval history.', 'fp-publisher'));
        }

        $updated = $wpdb->update(
            $table,
            [
                'status' => $targetStatus,
                'approvals_json' => $encodedApprovals,
                'updated_at' => $timestamp->format('Y-m-d H:i:s'),
            ],
            ['id' => $planId],
            ['%s', '%s', '%s'],
            ['%d']
        );

            if ($updated === false || $updated <= 0) {
                throw new RuntimeException(__('Unable to update the plan status.', 'fp-publisher'));
            }

            // Commit transaction
            $wpdb->query('COMMIT');

            return [
                'id' => (int) $row['id'],
                'status' => $targetStatus,
                'brand' => (string) $row['brand'],
                'approvals' => $approvals,
                'updated_at' => $timestamp->format(DateTimeInterface::ATOM),
            ];
        } catch (\Throwable $e) {
            // Rollback on any error
            $wpdb->query('ROLLBACK');

            // Re-throw the exception
            throw $e;
        }
    }
}
