<?php

declare(strict_types=1);

namespace FP\Publisher\Domain;

use DateTimeImmutable;
use FP\Publisher\Support\Arr;
use FP\Publisher\Support\Dates;
use FP\Publisher\Support\Validation;
use InvalidArgumentException;

use function array_map;
use function array_values;
use function trim;

final class PostPlan
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_READY = 'ready';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_FAILED = 'failed';

    private ?int $id;
    private string $brand;
    /** @var array<int, string> */
    private array $channels;
    /** @var array<int, ScheduledSlot> */
    private array $slots;
    /** @var array<int, AssetRef> */
    private array $assets;
    private Template $template;
    private string $status;
    private ?DateTimeImmutable $createdAt;
    private ?DateTimeImmutable $updatedAt;
    private ?string $igFirstComment;

    private function __construct(
        ?int $id,
        string $brand,
        array $channels,
        array $slots,
        array $assets,
        Template $template,
        string $status,
        ?string $igFirstComment,
        ?DateTimeImmutable $createdAt,
        ?DateTimeImmutable $updatedAt
    ) {
        $this->id = $id;
        $this->brand = $brand;
        $this->channels = $channels;
        $this->slots = $slots;
        $this->assets = $assets;
        $this->template = $template;
        $this->status = $status;
        $this->igFirstComment = $igFirstComment;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    public static function create(array $payload): self
    {
        return Validation::guard(static function () use ($payload): self {
            $id = isset($payload['id']) ? Validation::positiveInt($payload['id'], 'plan.id', true) : null;
            $brand = Validation::string($payload['brand'] ?? '', 'plan.brand');
            $channels = Validation::arrayOfStrings($payload['channels'] ?? [], 'plan.channels');
            if ($channels === []) {
                throw new InvalidArgumentException('plan.channels must contain at least one channel.');
            }

            $slots = Validation::arrayOf(
                (array) ($payload['slots'] ?? []),
                static fn (mixed $slot): ScheduledSlot => ScheduledSlot::create((array) $slot),
                'plan.slots'
            );

            if ($slots === []) {
                throw new InvalidArgumentException('plan.slots must contain at least one scheduled slot.');
            }

            $assets = Validation::arrayOf(
                (array) ($payload['assets'] ?? []),
                static fn (mixed $asset): AssetRef => AssetRef::create((array) $asset),
                'plan.assets'
            );

            $template = Template::create((array) Arr::get($payload, 'template', []));
            $status = Validation::enum($payload['status'] ?? self::STATUS_DRAFT, self::statuses(), 'plan.status');
            $igFirstComment = Validation::nullableString($payload['ig_first_comment'] ?? null, 'plan.ig_first_comment');
            if ($igFirstComment !== null) {
                $igFirstComment = trim($igFirstComment);
            }
            $createdAt = isset($payload['created_at'])
                ? Dates::ensure((string) $payload['created_at'])
                : null;
            $updatedAt = isset($payload['updated_at'])
                ? Dates::ensure((string) $payload['updated_at'])
                : null;

            return new self($id, $brand, $channels, array_values($slots), array_values($assets), $template, $status, $igFirstComment, $createdAt, $updatedAt);
        });
    }

    /**
     * @return array<int, string>
     */
    public static function statuses(): array
    {
        return [
            self::STATUS_DRAFT,
            self::STATUS_READY,
            self::STATUS_APPROVED,
            self::STATUS_SCHEDULED,
            self::STATUS_PUBLISHED,
            self::STATUS_FAILED,
        ];
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function brand(): string
    {
        return $this->brand;
    }

    /**
     * @return array<int, string>
     */
    public function channels(): array
    {
        return $this->channels;
    }

    /**
     * @return array<int, ScheduledSlot>
     */
    public function slots(): array
    {
        return $this->slots;
    }

    /**
     * @return array<int, AssetRef>
     */
    public function assets(): array
    {
        return $this->assets;
    }

    public function template(): Template
    {
        return $this->template;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function createdAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function igFirstComment(): ?string
    {
        return $this->igFirstComment !== null && $this->igFirstComment !== ''
            ? $this->igFirstComment
            : null;
    }

    public function withStatus(string $status): self
    {
        $status = Validation::enum($status, self::statuses(), 'plan.status');

        $clone = clone $this;
        $clone->status = $status;

        return $clone;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'brand' => $this->brand,
            'channels' => $this->channels,
            'slots' => array_map(static fn (ScheduledSlot $slot): array => $slot->toArray(), $this->slots),
            'assets' => array_map(static fn (AssetRef $asset): array => $asset->toArray(), $this->assets),
            'template' => [
                'id' => $this->template->id(),
                'name' => $this->template->name(),
                'body' => $this->template->body(),
                'placeholders' => $this->template->placeholders(),
                'channel_overrides' => $this->template->channelOverrides(),
            ],
            'status' => $this->status,
            'ig_first_comment' => $this->igFirstComment,
            'created_at' => $this->createdAt?->format(DateTimeImmutable::ATOM),
            'updated_at' => $this->updatedAt?->format(DateTimeImmutable::ATOM),
        ];
    }
}
