<?php

declare(strict_types=1);

namespace FP\Publisher\Domain;

use FP\Publisher\Support\Arr;
use FP\Publisher\Support\Validation;

final class Template
{
    private int $id;
    private string $name;
    private string $body;
    private array $placeholders;
    private array $channelOverrides;

    private function __construct(int $id, string $name, string $body, array $placeholders, array $channelOverrides)
    {
        $this->id = $id;
        $this->name = $name;
        $this->body = $body;
        $this->placeholders = $placeholders;
        $this->channelOverrides = $channelOverrides;
    }

    public static function create(array $payload): self
    {
        return Validation::guard(static function () use ($payload): self {
            $id = Validation::positiveInt($payload['id'] ?? 0, 'template.id');
            $name = Validation::string($payload['name'] ?? '', 'template.name');
            $body = Validation::string($payload['body'] ?? '', 'template.body', true);
            $placeholders = Validation::arrayOfStrings($payload['placeholders'] ?? [], 'template.placeholders', true);
            $overrides = Arr::map(
                (array) ($payload['channel_overrides'] ?? []),
                static function (mixed $value): array {
                    return Validation::array($value ?? [], 'template.channel_overrides');
                }
            );

            return new self($id, $name, $body, $placeholders, $overrides);
        });
    }

    public function id(): int
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function body(): string
    {
        return $this->body;
    }

    /**
     * @return array<int, string>
     */
    public function placeholders(): array
    {
        return $this->placeholders;
    }

    /**
     * @return array<string, array>
     */
    public function channelOverrides(): array
    {
        return $this->channelOverrides;
    }
}
