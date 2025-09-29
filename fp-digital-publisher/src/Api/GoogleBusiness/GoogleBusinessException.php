<?php

declare(strict_types=1);

namespace FP\Publisher\Api\GoogleBusiness;

use RuntimeException;

use function in_array;
use function is_numeric;
use function sanitize_key;

final class GoogleBusinessException extends RuntimeException
{
    private string $reason;
    private int $statusCode;
    private bool $retryable;

    private function __construct(string $message, int $statusCode, string $reason, bool $retryable)
    {
        parent::__construct($message, $statusCode);
        $this->statusCode = $statusCode;
        $this->reason = $reason;
        $this->retryable = $retryable;
    }

    public static function invalidRequest(string $message): self
    {
        return new self($message, 400, 'invalid_request', false);
    }

    public static function unauthorized(string $message): self
    {
        return new self($message, 401, 'unauthorized', false);
    }

    public static function unexpected(string $message): self
    {
        return new self($message, 500, 'unexpected_error', true);
    }

    public static function fromApi(string $message, int|string|null $statusCode, string $reason, bool $retryable = false): self
    {
        $code = is_numeric($statusCode) ? (int) $statusCode : 500;
        $normalizedReason = sanitize_key($reason !== '' ? $reason : 'unknown_error');
        $retryable = $retryable || self::shouldRetry($code, $normalizedReason);

        return new self($message, $code, $normalizedReason, $retryable);
    }

    public function isRetryable(): bool
    {
        return $this->retryable;
    }

    public function reason(): string
    {
        return $this->reason;
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    private static function shouldRetry(int $statusCode, string $reason): bool
    {
        if (in_array($statusCode, [408, 409, 423, 425, 429, 500, 502, 503, 504], true)) {
            return true;
        }

        return in_array($reason, [
            'internal',
            'backend_error',
            'backendunavailable',
            'serviceunavailable',
            'resource_exhausted',
            'aborted',
        ], true);
    }
}
