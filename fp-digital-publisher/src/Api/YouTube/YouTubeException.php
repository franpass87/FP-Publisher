<?php

declare(strict_types=1);

namespace FP\Publisher\Api\YouTube;

use RuntimeException;

use function in_array;
use function is_numeric;

final class YouTubeException extends RuntimeException
{
    private string $errorCode;
    private int $statusCode;
    private bool $retryable;

    private function __construct(string $message, int $statusCode, string $errorCode, bool $retryable)
    {
        parent::__construct($message, $statusCode);
        $this->statusCode = $statusCode;
        $this->errorCode = $errorCode;
        $this->retryable = $retryable;
    }

    public static function invalidRequest(string $message): self
    {
        return new self($message, 400, 'invalid_request', false);
    }

    public static function unexpected(string $message): self
    {
        return new self($message, 500, 'unexpected_error', true);
    }

    public static function fromApi(string $message, int|string|null $statusCode, string $errorCode, bool $retryable = false): self
    {
        $code = is_numeric($statusCode) ? (int) $statusCode : 500;
        $retryable = $retryable || self::shouldRetry($code, $errorCode);

        return new self($message, $code, $errorCode, $retryable);
    }

    public function isRetryable(): bool
    {
        return $this->retryable;
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    private static function shouldRetry(int $statusCode, string $errorCode): bool
    {
        if (in_array($statusCode, [408, 409, 423, 425, 429, 500, 502, 503, 504], true)) {
            return true;
        }

        return in_array($errorCode, [
            'rate_limit_exceeded',
            'quota_exceeded',
            'backend_error',
            'internal_error',
            'backendUnavailable',
        ], true);
    }
}
