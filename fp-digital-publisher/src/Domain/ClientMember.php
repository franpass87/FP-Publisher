<?php

declare(strict_types=1);

namespace FP\Publisher\Domain;

use DateTimeImmutable;
use FP\Publisher\Support\Dates;
use FP\Publisher\Support\Validation;

use function is_array;

final class ClientMember
{
    public const ROLE_OWNER = 'owner';
    public const ROLE_ADMIN = 'admin';
    public const ROLE_EDITOR = 'editor';
    public const ROLE_CONTRIBUTOR = 'contributor';
    public const ROLE_VIEWER = 'viewer';

    public const STATUS_PENDING = 'pending';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_SUSPENDED = 'suspended';

    private ?int $id;
    private int $clientId;
    private int $userId;
    private string $role;
    private ?int $invitedBy;
    private DateTimeImmutable $invitedAt;
    private ?DateTimeImmutable $acceptedAt;
    private string $status;
    /** @var array<string, mixed> */
    private array $permissions;

    /**
     * @param array<string, mixed> $permissions
     */
    private function __construct(
        ?int $id,
        int $clientId,
        int $userId,
        string $role,
        ?int $invitedBy,
        DateTimeImmutable $invitedAt,
        ?DateTimeImmutable $acceptedAt,
        string $status,
        array $permissions
    ) {
        $this->id = $id;
        $this->clientId = $clientId;
        $this->userId = $userId;
        $this->role = $role;
        $this->invitedBy = $invitedBy;
        $this->invitedAt = $invitedAt;
        $this->acceptedAt = $acceptedAt;
        $this->status = $status;
        $this->permissions = $permissions;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function create(array $payload): self
    {
        return Validation::guard(static function () use ($payload): self {
            $id = isset($payload['id']) ? Validation::positiveInt($payload['id'], 'member.id', true) : null;
            $clientId = Validation::positiveInt($payload['client_id'] ?? 0, 'member.client_id');
            $userId = Validation::positiveInt($payload['user_id'] ?? 0, 'member.user_id');

            $role = Validation::enum(
                $payload['role'] ?? self::ROLE_VIEWER,
                self::roles(),
                'member.role'
            );

            $invitedBy = isset($payload['invited_by'])
                ? Validation::positiveInt($payload['invited_by'], 'member.invited_by', true)
                : null;

            $invitedAt = isset($payload['invited_at'])
                ? Dates::ensure((string) $payload['invited_at'])
                : Dates::now('UTC');

            $acceptedAt = isset($payload['accepted_at'])
                ? Dates::ensure((string) $payload['accepted_at'])
                : null;

            $status = Validation::enum(
                $payload['status'] ?? self::STATUS_PENDING,
                self::statuses(),
                'member.status'
            );

            $permissions = is_array($payload['permissions'] ?? null) ? $payload['permissions'] : [];

            return new self(
                $id,
                $clientId,
                $userId,
                $role,
                $invitedBy,
                $invitedAt,
                $acceptedAt,
                $status,
                $permissions
            );
        });
    }

    /**
     * @return array<int, string>
     */
    public static function roles(): array
    {
        return [
            self::ROLE_OWNER,
            self::ROLE_ADMIN,
            self::ROLE_EDITOR,
            self::ROLE_CONTRIBUTOR,
            self::ROLE_VIEWER,
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function statuses(): array
    {
        return [
            self::STATUS_PENDING,
            self::STATUS_ACTIVE,
            self::STATUS_SUSPENDED,
        ];
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function clientId(): int
    {
        return $this->clientId;
    }

    public function userId(): int
    {
        return $this->userId;
    }

    public function role(): string
    {
        return $this->role;
    }

    public function invitedBy(): ?int
    {
        return $this->invitedBy;
    }

    public function invitedAt(): DateTimeImmutable
    {
        return $this->invitedAt;
    }

    public function acceptedAt(): ?DateTimeImmutable
    {
        return $this->acceptedAt;
    }

    public function status(): string
    {
        return $this->status;
    }

    /**
     * @return array<string, mixed>
     */
    public function permissions(): array
    {
        return $this->permissions;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isOwner(): bool
    {
        return $this->role === self::ROLE_OWNER;
    }

    public function canPublish(): bool
    {
        return in_array($this->role, [self::ROLE_OWNER, self::ROLE_ADMIN, self::ROLE_EDITOR], true);
    }

    public function canManageTeam(): bool
    {
        return in_array($this->role, [self::ROLE_OWNER, self::ROLE_ADMIN], true);
    }

    public function canManageAccounts(): bool
    {
        return in_array($this->role, [self::ROLE_OWNER, self::ROLE_ADMIN], true);
    }

    public function canViewAnalytics(): bool
    {
        return in_array($this->role, [self::ROLE_OWNER, self::ROLE_ADMIN, self::ROLE_EDITOR, self::ROLE_VIEWER], true);
    }

    public function canExportAnalytics(): bool
    {
        return in_array($this->role, [self::ROLE_OWNER, self::ROLE_ADMIN], true);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'client_id' => $this->clientId,
            'user_id' => $this->userId,
            'role' => $this->role,
            'invited_by' => $this->invitedBy,
            'invited_at' => $this->invitedAt->format(DateTimeImmutable::ATOM),
            'accepted_at' => $this->acceptedAt?->format(DateTimeImmutable::ATOM),
            'status' => $this->status,
            'permissions' => $this->permissions,
            'can_publish' => $this->canPublish(),
            'can_manage_team' => $this->canManageTeam(),
            'can_manage_accounts' => $this->canManageAccounts(),
            'can_view_analytics' => $this->canViewAnalytics(),
            'can_export_analytics' => $this->canExportAnalytics(),
        ];
    }
}
