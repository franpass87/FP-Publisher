<?php

declare(strict_types=1);

namespace FP\Publisher\Api\Meta;

use RuntimeException;

use function in_array;

final class MetaException extends RuntimeException
{
    private string $errorType;
    private int $errorCode;
    private int $statusCode;
    private bool $retryable;

    private function __construct(string $message, int $statusCode, string $errorType, int $errorCode, bool $retryable)
    {
        parent::__construct($message, $statusCode);
        $this->statusCode = $statusCode;
        $this->errorType = $errorType;
        $this->errorCode = $errorCode;
        $this->retryable = $retryable;
    }

    public static function invalidRequest(string $message): self
    {
        return new self($message, 400, 'invalid_request', 0, false);
    }

    public static function unexpected(string $message): self
    {
        return new self($message, 500, 'unexpected', 0, true);
    }

    public static function fromGraph(string $message, int $statusCode, string $errorType, int $errorCode): self
    {
        $retryable = self::determineRetryable($statusCode, $errorCode);

        return new self($message, $statusCode, $errorType, $errorCode, $retryable);
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    public function errorType(): string
    {
        return $this->errorType;
    }

    public function errorCode(): int
    {
        return $this->errorCode;
    }

    public function isRetryable(): bool
    {
        return $this->retryable;
    }

    private static function determineRetryable(int $statusCode, int $errorCode): bool
    {
        if (in_array($statusCode, [408, 409, 423, 429, 500, 502, 503, 504], true)) {
            return true;
        }

        return in_array($errorCode, [1, 2, 4, 17, 32, 613], true);
    }
}
