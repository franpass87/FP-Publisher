<?php

declare(strict_types=1);

namespace FP\Publisher\Domain;

use DateTimeImmutable;
use FP\Publisher\Support\Dates;
use FP\Publisher\Support\Validation;
use InvalidArgumentException;

use function is_array;
use function sanitize_title;
use function trim;

final class Client
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_PAUSED = 'paused';
    public const STATUS_ARCHIVED = 'archived';

    public const PLAN_FREE = 'free';
    public const PLAN_BASIC = 'basic';
    public const PLAN_PRO = 'pro';
    public const PLAN_AGENCY = 'agency';
    public const PLAN_ENTERPRISE = 'enterprise';

    private ?int $id;
    private string $name;
    private string $slug;
    private ?string $logoUrl;
    private ?string $website;
    private ?string $industry;
    private string $timezone;
    private string $color;
    private string $status;
    private string $billingPlan;
    private ?DateTimeImmutable $billingCycleStart;
    private ?DateTimeImmutable $billingCycleEnd;
    /** @var array<string, mixed> */
    private array $meta;
    private ?DateTimeImmutable $createdAt;
    private ?DateTimeImmutable $updatedAt;

    /**
     * @param array<string, mixed> $meta
     */
    private function __construct(
        ?int $id,
        string $name,
        string $slug,
        ?string $logoUrl,
        ?string $website,
        ?string $industry,
        string $timezone,
        string $color,
        string $status,
        string $billingPlan,
        ?DateTimeImmutable $billingCycleStart,
        ?DateTimeImmutable $billingCycleEnd,
        array $meta,
        ?DateTimeImmutable $createdAt,
        ?DateTimeImmutable $updatedAt
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->slug = $slug;
        $this->logoUrl = $logoUrl;
        $this->website = $website;
        $this->industry = $industry;
        $this->timezone = $timezone;
        $this->color = $color;
        $this->status = $status;
        $this->billingPlan = $billingPlan;
        $this->billingCycleStart = $billingCycleStart;
        $this->billingCycleEnd = $billingCycleEnd;
        $this->meta = $meta;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function create(array $payload): self
    {
        return Validation::guard(static function () use ($payload): self {
            $id = isset($payload['id']) ? Validation::positiveInt($payload['id'], 'client.id', true) : null;
            $name = Validation::string($payload['name'] ?? '', 'client.name');
            
            $slug = isset($payload['slug']) && trim((string) $payload['slug']) !== ''
                ? self::sanitizeSlug((string) $payload['slug'])
                : self::generateSlug($name);

            $logoUrl = Validation::nullableString($payload['logo_url'] ?? null, 'client.logo_url');
            $website = Validation::nullableString($payload['website'] ?? null, 'client.website');
            $industry = Validation::nullableString($payload['industry'] ?? null, 'client.industry');
            
            $timezone = isset($payload['timezone']) && trim((string) $payload['timezone']) !== ''
                ? (string) $payload['timezone']
                : 'UTC';
            
            $color = isset($payload['color']) && self::isValidColor((string) $payload['color'])
                ? (string) $payload['color']
                : '#666666';

            $status = Validation::enum(
                $payload['status'] ?? self::STATUS_ACTIVE,
                self::statuses(),
                'client.status'
            );

            $billingPlan = Validation::enum(
                $payload['billing_plan'] ?? self::PLAN_FREE,
                self::billingPlans(),
                'client.billing_plan'
            );

            $billingCycleStart = isset($payload['billing_cycle_start'])
                ? Dates::ensure((string) $payload['billing_cycle_start'])
                : null;

            $billingCycleEnd = isset($payload['billing_cycle_end'])
                ? Dates::ensure((string) $payload['billing_cycle_end'])
                : null;

            $meta = is_array($payload['meta'] ?? null) ? $payload['meta'] : [];

            $createdAt = isset($payload['created_at'])
                ? Dates::ensure((string) $payload['created_at'])
                : null;

            $updatedAt = isset($payload['updated_at'])
                ? Dates::ensure((string) $payload['updated_at'])
                : null;

            return new self(
                $id,
                $name,
                $slug,
                $logoUrl,
                $website,
                $industry,
                $timezone,
                $color,
                $status,
                $billingPlan,
                $billingCycleStart,
                $billingCycleEnd,
                $meta,
                $createdAt,
                $updatedAt
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
            self::STATUS_PAUSED,
            self::STATUS_ARCHIVED,
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function billingPlans(): array
    {
        return [
            self::PLAN_FREE,
            self::PLAN_BASIC,
            self::PLAN_PRO,
            self::PLAN_AGENCY,
            self::PLAN_ENTERPRISE,
        ];
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function slug(): string
    {
        return $this->slug;
    }

    public function logoUrl(): ?string
    {
        return $this->logoUrl;
    }

    public function website(): ?string
    {
        return $this->website;
    }

    public function industry(): ?string
    {
        return $this->industry;
    }

    public function timezone(): string
    {
        return $this->timezone;
    }

    public function color(): string
    {
        return $this->color;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function billingPlan(): string
    {
        return $this->billingPlan;
    }

    public function billingCycleStart(): ?DateTimeImmutable
    {
        return $this->billingCycleStart;
    }

    public function billingCycleEnd(): ?DateTimeImmutable
    {
        return $this->billingCycleEnd;
    }

    /**
     * @return array<string, mixed>
     */
    public function meta(): array
    {
        return $this->meta;
    }

    public function createdAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isPaused(): bool
    {
        return $this->status === self::STATUS_PAUSED;
    }

    public function isArchived(): bool
    {
        return $this->status === self::STATUS_ARCHIVED;
    }

    public function getMaxChannels(): int
    {
        return match ($this->billingPlan) {
            self::PLAN_FREE => 2,
            self::PLAN_BASIC => 4,
            self::PLAN_PRO => 6,
            self::PLAN_AGENCY, self::PLAN_ENTERPRISE => PHP_INT_MAX,
            default => 0,
        };
    }

    public function getMonthlyPostLimit(): int
    {
        return match ($this->billingPlan) {
            self::PLAN_FREE => 10,
            self::PLAN_BASIC => 50,
            self::PLAN_PRO, self::PLAN_AGENCY, self::PLAN_ENTERPRISE => PHP_INT_MAX,
            default => 0,
        };
    }

    public function getMaxTeamMembers(): int
    {
        return match ($this->billingPlan) {
            self::PLAN_FREE => 1,
            self::PLAN_BASIC => 3,
            self::PLAN_PRO => 10,
            self::PLAN_AGENCY, self::PLAN_ENTERPRISE => PHP_INT_MAX,
            default => 1,
        };
    }

    public function getStorageLimitBytes(): int
    {
        return match ($this->billingPlan) {
            self::PLAN_FREE => 1024 * 1024 * 1024, // 1 GB
            self::PLAN_BASIC => 5 * 1024 * 1024 * 1024, // 5 GB
            self::PLAN_PRO => 20 * 1024 * 1024 * 1024, // 20 GB
            self::PLAN_AGENCY => 100 * 1024 * 1024 * 1024, // 100 GB
            self::PLAN_ENTERPRISE => PHP_INT_MAX,
            default => 0,
        };
    }

    public function canPublishToChannels(int $count): bool
    {
        return $count <= $this->getMaxChannels();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'logo_url' => $this->logoUrl,
            'website' => $this->website,
            'industry' => $this->industry,
            'timezone' => $this->timezone,
            'color' => $this->color,
            'status' => $this->status,
            'billing_plan' => $this->billingPlan,
            'billing_cycle_start' => $this->billingCycleStart?->format('Y-m-d'),
            'billing_cycle_end' => $this->billingCycleEnd?->format('Y-m-d'),
            'limits' => [
                'max_channels' => $this->getMaxChannels(),
                'max_posts_monthly' => $this->getMonthlyPostLimit(),
                'max_team_members' => $this->getMaxTeamMembers(),
                'storage_bytes' => $this->getStorageLimitBytes(),
            ],
            'meta' => $this->meta,
            'created_at' => $this->createdAt?->format(DateTimeImmutable::ATOM),
            'updated_at' => $this->updatedAt?->format(DateTimeImmutable::ATOM),
        ];
    }

    private static function generateSlug(string $name): string
    {
        if (trim($name) === '') {
            throw new InvalidArgumentException('Client name cannot be empty');
        }

        $slug = sanitize_title($name);
        
        if ($slug === '') {
            throw new InvalidArgumentException('Cannot generate valid slug from name');
        }

        return $slug;
    }

    private static function sanitizeSlug(string $slug): string
    {
        $sanitized = sanitize_title($slug);
        
        if ($sanitized === '') {
            throw new InvalidArgumentException('Invalid slug provided');
        }

        return $sanitized;
    }

    private static function isValidColor(string $color): bool
    {
        return (bool) preg_match('/^#[0-9A-Fa-f]{6}$/', $color);
    }
}
