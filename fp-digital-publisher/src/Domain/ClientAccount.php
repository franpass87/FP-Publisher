<?php

declare(strict_types=1);

namespace FP\Publisher\Domain;

use DateTimeImmutable;
use FP\Publisher\Support\Dates;
use FP\Publisher\Support\Validation;

use function is_array;

final class ClientAccount
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_DISCONNECTED = 'disconnected';
    public const STATUS_EXPIRED = 'expired';

    private ?int $id;
    private int $clientId;
    private string $channel;
    private string $accountIdentifier;
    private ?string $accountName;
    private ?string $accountAvatar;
    private string $status;
    private DateTimeImmutable $connectedAt;
    private ?DateTimeImmutable $lastSyncedAt;
    /** @var array<string, mixed> */
    private array $tokens;
    /** @var array<string, mixed> */
    private array $meta;

    /**
     * @param array<string, mixed> $tokens
     * @param array<string, mixed> $meta
     */
    private function __construct(
        ?int $id,
        int $clientId,
        string $channel,
        string $accountIdentifier,
        ?string $accountName,
        ?string $accountAvatar,
        string $status,
        DateTimeImmutable $connectedAt,
        ?DateTimeImmutable $lastSyncedAt,
        array $tokens,
        array $meta
    ) {
        $this->id = $id;
        $this->clientId = $clientId;
        $this->channel = $channel;
        $this->accountIdentifier = $accountIdentifier;
        $this->accountName = $accountName;
        $this->accountAvatar = $accountAvatar;
        $this->status = $status;
        $this->connectedAt = $connectedAt;
        $this->lastSyncedAt = $lastSyncedAt;
        $this->tokens = $tokens;
        $this->meta = $meta;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function create(array $payload): self
    {
        return Validation::guard(static function () use ($payload): self {
            $id = isset($payload['id']) ? Validation::positiveInt($payload['id'], 'account.id', true) : null;
            $clientId = Validation::positiveInt($payload['client_id'] ?? 0, 'account.client_id');
            $channel = Validation::string($payload['channel'] ?? '', 'account.channel');
            $accountIdentifier = Validation::string($payload['account_identifier'] ?? '', 'account.account_identifier');
            $accountName = Validation::nullableString($payload['account_name'] ?? null, 'account.account_name');
            $accountAvatar = Validation::nullableString($payload['account_avatar'] ?? null, 'account.account_avatar');

            $status = Validation::enum(
                $payload['status'] ?? self::STATUS_ACTIVE,
                self::statuses(),
                'account.status'
            );

            $connectedAt = isset($payload['connected_at'])
                ? Dates::ensure((string) $payload['connected_at'])
                : Dates::now('UTC');

            $lastSyncedAt = isset($payload['last_synced_at'])
                ? Dates::ensure((string) $payload['last_synced_at'])
                : null;

            $tokens = is_array($payload['tokens'] ?? null) ? $payload['tokens'] : [];
            $meta = is_array($payload['meta'] ?? null) ? $payload['meta'] : [];

            return new self(
                $id,
                $clientId,
                $channel,
                $accountIdentifier,
                $accountName,
                $accountAvatar,
                $status,
                $connectedAt,
                $lastSyncedAt,
                $tokens,
                $meta
            );
        });
    }

    /**
     * @return array<int, string>
     */
    public static function statuses(): array
    {
        return [
            self::STATUS_ACTIVE,
            self::STATUS_DISCONNECTED,
            self::STATUS_EXPIRED,
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

    public function channel(): string
    {
        return $this->channel;
    }

    public function accountIdentifier(): string
    {
        return $this->accountIdentifier;
    }

    public function accountName(): ?string
    {
        return $this->accountName;
    }

    public function accountAvatar(): ?string
    {
        return $this->accountAvatar;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function connectedAt(): DateTimeImmutable
    {
        return $this->connectedAt;
    }

    public function lastSyncedAt(): ?DateTimeImmutable
    {
        return $this->lastSyncedAt;
    }

    /**
     * @return array<string, mixed>
     */
    public function tokens(): array
    {
        return $this->tokens;
    }

    /**
     * @return array<string, mixed>
     */
    public function meta(): array
    {
        return $this->meta;
    }

    public function isConnected(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isExpired(): bool
    {
        return $this->status === self::STATUS_EXPIRED;
    }

    public function getAccessToken(): ?string
    {
        $token = $this->tokens['access_token'] ?? null;

        return is_string($token) && $token !== '' ? $token : null;
    }

    public function getRefreshToken(): ?string
    {
        $token = $this->tokens['refresh_token'] ?? null;

        return is_string($token) && $token !== '' ? $token : null;
    }

    public function getTokenExpiry(): ?DateTimeImmutable
    {
        $expiry = $this->tokens['expires_at'] ?? null;

        if (! is_string($expiry) || $expiry === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($expiry);
        } catch (\Exception) {
            return null;
        }
    }

    public function needsTokenRefresh(): bool
    {
        $expiry = $this->getTokenExpiry();

        if ($expiry === null) {
            return false;
        }

        $now = Dates::now('UTC');
        $buffer = new \DateInterval('PT1H'); // 1 hour buffer

        return $expiry <= $now->add($buffer);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'client_id' => $this->clientId,
            'channel' => $this->channel,
            'account_identifier' => $this->accountIdentifier,
            'account_name' => $this->accountName,
            'account_avatar' => $this->accountAvatar,
            'status' => $this->status,
            'connected_at' => $this->connectedAt->format(DateTimeImmutable::ATOM),
            'last_synced_at' => $this->lastSyncedAt?->format(DateTimeImmutable::ATOM),
            'token_expiry' => $this->getTokenExpiry()?->format(DateTimeImmutable::ATOM),
            'needs_refresh' => $this->needsTokenRefresh(),
            'meta' => $this->meta,
        ];
    }

    private static function sanitizeSlug(string $slug): string
    {
        return sanitize_title($slug);
    }

    private static function generateSlug(string $name): string
    {
        return sanitize_title($name);
    }

    private static function isValidColor(string $color): bool
    {
        return (bool) preg_match('/^#[0-9A-Fa-f]{6}$/', $color);
    }
}
