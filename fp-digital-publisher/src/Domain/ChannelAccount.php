<?php

declare(strict_types=1);

namespace FP\Publisher\Domain;

use DateTimeImmutable;
use FP\Publisher\Support\Dates;
use FP\Publisher\Support\Validation;

final class ChannelAccount
{
    private const STATUS_ACTIVE = 'active';
    private const STATUS_INACTIVE = 'inactive';
    private const STATUS_EXPIRED = 'expired';

    private string $service;
    private string $accountId;
    private string $displayName;
    private string $status;
    private array $scopes;
    private ?DateTimeImmutable $tokenExpiresAt;

    private function __construct(
        string $service,
        string $accountId,
        string $displayName,
        string $status,
        array $scopes,
        ?DateTimeImmutable $tokenExpiresAt
    ) {
        $this->service = $service;
        $this->accountId = $accountId;
        $this->displayName = $displayName;
        $this->status = $status;
        $this->scopes = $scopes;
        $this->tokenExpiresAt = $tokenExpiresAt;
    }

    public static function create(array $payload): self
    {
        return Validation::guard(static function () use ($payload): self {
            $service = Validation::string($payload['service'] ?? '', 'account.service');
            $accountId = Validation::string($payload['account_id'] ?? '', 'account.account_id');
            $displayName = Validation::string($payload['display_name'] ?? '', 'account.display_name');
            $status = Validation::enum(
                $payload['status'] ?? self::STATUS_ACTIVE,
                self::statuses(),
                'account.status'
            );
            $scopes = Validation::arrayOfStrings($payload['scopes'] ?? [], 'account.scopes');
            $expiresAt = null;

            if (isset($payload['token_expires_at'])) {
                $expiresAt = Dates::ensure((string) $payload['token_expires_at']);
            }

            return new self($service, $accountId, $displayName, $status, $scopes, $expiresAt);
        });
    }

    public function service(): string
    {
        return $this->service;
    }

    public function accountId(): string
    {
        return $this->accountId;
    }

    public function displayName(): string
    {
        return $this->displayName;
    }

    public function status(): string
    {
        return $this->status;
    }

    /**
     * @return array<int, string>
     */
    public function scopes(): array
    {
        return $this->scopes;
    }

    public function tokenExpiresAt(): ?DateTimeImmutable
    {
        return $this->tokenExpiresAt;
    }

    /**
     * @return array<int, string>
     */
    public static function statuses(): array
    {
        return [
            self::STATUS_ACTIVE,
            self::STATUS_INACTIVE,
            self::STATUS_EXPIRED,
        ];
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE && ! $this->isExpired();
    }

    public function isExpired(): bool
    {
        return $this->tokenExpiresAt !== null && $this->tokenExpiresAt <= Dates::now();
    }

    public function toArray(): array
    {
        return [
            'service' => $this->service,
            'account_id' => $this->accountId,
            'display_name' => $this->displayName,
            'status' => $this->status,
            'scopes' => $this->scopes,
            'token_expires_at' => $this->tokenExpiresAt?->format(DateTimeImmutable::ATOM),
        ];
    }
}
