<?php

declare(strict_types=1);

namespace FP\Publisher\Domain;

use DateTimeImmutable;
use FP\Publisher\Support\Dates;
use FP\Publisher\Support\Validation;

final class ScheduledSlot
{
    private string $channel;
    private DateTimeImmutable $scheduledAt;
    private ?DateTimeImmutable $publishUntil;
    private ?int $duration;

    private function __construct(string $channel, DateTimeImmutable $scheduledAt, ?DateTimeImmutable $publishUntil, ?int $duration)
    {
        $this->channel = $channel;
        $this->scheduledAt = $scheduledAt;
        $this->publishUntil = $publishUntil;
        $this->duration = $duration;
    }

    public static function create(array $payload): self
    {
        return Validation::guard(static function () use ($payload): self {
            $channel = Validation::string($payload['channel'] ?? '', 'slot.channel');
            $scheduledAt = Dates::ensure((string) ($payload['scheduled_at'] ?? 'now'), Dates::DEFAULT_TZ);
            $publishUntil = null;

            if (! empty($payload['publish_until'])) {
                $publishUntil = Dates::ensure((string) $payload['publish_until'], Dates::DEFAULT_TZ);
            }

            $duration = null;
            if (isset($payload['duration_minutes'])) {
                $duration = Validation::positiveInt($payload['duration_minutes'], 'slot.duration_minutes', true);
            }

            return new self($channel, $scheduledAt, $publishUntil, $duration);
        });
    }

    public function channel(): string
    {
        return $this->channel;
    }

    public function scheduledAt(): DateTimeImmutable
    {
        return $this->scheduledAt;
    }

    public function publishUntil(): ?DateTimeImmutable
    {
        return $this->publishUntil;
    }

    public function duration(): ?int
    {
        return $this->duration;
    }

    public function toArray(): array
    {
        return [
            'channel' => $this->channel,
            'scheduled_at' => $this->scheduledAt->format(DateTimeImmutable::ATOM),
            'publish_until' => $this->publishUntil?->format(DateTimeImmutable::ATOM),
            'duration_minutes' => $this->duration,
        ];
    }
}
