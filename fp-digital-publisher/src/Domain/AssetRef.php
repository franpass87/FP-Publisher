<?php

declare(strict_types=1);

namespace FP\Publisher\Domain;

use FP\Publisher\Support\Arr;
use FP\Publisher\Support\Validation;

use function is_array;

use function strtolower;

final class AssetRef
{
    private int $id;
    private string $source;
    private string $reference;
    private string $mimeType;
    private int $bytes;
    private ?string $checksum;
    private ?string $altText;
    /** @var array<string, mixed> */
    private array $meta;

    /**
     * @param array<string, mixed> $meta
     */
    private function __construct(
        int $id,
        string $source,
        string $reference,
        string $mimeType,
        int $bytes,
        ?string $checksum,
        ?string $altText,
        array $meta
    )
    {
        $this->id = $id;
        $this->source = $source;
        $this->reference = $reference;
        $this->mimeType = strtolower($mimeType);
        $this->bytes = $bytes;
        $this->checksum = $checksum;
        $this->altText = $altText;
        $this->meta = $meta;
    }

    public static function create(array $payload): self
    {
        return Validation::guard(static function () use ($payload): self {
            $id = Validation::positiveInt($payload['id'] ?? 0, 'asset.id');
            $source = Validation::string($payload['source'] ?? '', 'asset.source');
            $reference = Validation::string($payload['reference'] ?? '', 'asset.reference');
            $mime = Validation::string($payload['mime_type'] ?? '', 'asset.mime_type');
            $bytes = Validation::positiveInt($payload['bytes'] ?? 0, 'asset.bytes', true);
            $checksum = Validation::nullableString($payload['checksum'] ?? null, 'asset.checksum');
            $meta = [];
            if (isset($payload['meta']) && is_array($payload['meta'])) {
                $meta = $payload['meta'];
            }

            $altText = Validation::nullableString(
                $payload['alt_text'] ?? Arr::get($meta, 'alt_text'),
                'asset.alt_text'
            );

            return new self($id, $source, $reference, $mime, $bytes, $checksum, $altText, $meta);
        });
    }

    public function id(): int
    {
        return $this->id;
    }

    public function source(): string
    {
        return $this->source;
    }

    public function reference(): string
    {
        return $this->reference;
    }

    public function mimeType(): string
    {
        return $this->mimeType;
    }

    public function bytes(): int
    {
        return $this->bytes;
    }

    public function checksum(): ?string
    {
        return $this->checksum;
    }

    public function altText(): ?string
    {
        return $this->altText !== null && $this->altText !== ''
            ? $this->altText
            : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function meta(): array
    {
        return $this->meta;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'source' => $this->source,
            'reference' => $this->reference,
            'mime_type' => $this->mimeType,
            'bytes' => $this->bytes,
            'checksum' => $this->checksum,
            'alt_text' => $this->altText,
            'meta' => $this->meta,
        ];
    }
}
