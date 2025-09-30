<?php

declare(strict_types=1);

namespace FP\Publisher\Support;

use Throwable;

use function in_array;
use function is_int;
use function is_numeric;
use function method_exists;
use function str_contains;
use function strtolower;
use function trim;

final class TransientErrorClassifier
{
    /**
     * @var int[]
     */
    private const RETRYABLE_SPECIAL_STATUS_CODES = [408, 423, 425, 429];

    /**
     * @var string[]
     */
    private const RETRYABLE_ERROR_CODE_HINTS = [
        'timeout',
        'temporar',
        'rate_limit',
        'rate-limit',
        'server_error',
        'server-error',
        'backend_error',
        'backend-error',
        'service_unavailable',
        'service-unavailable',
        'resource_exhausted',
        'resource-exhausted',
        'aborted',
        'conflict',
        'concurrent',
        'deadlock',
        'lock_wait_timeout',
        'lock-wait-timeout',
    ];

    /**
     * @var string[]
     */
    private const NON_RETRYABLE_ERROR_CODE_HINTS = [
        'invalid',
        'unauthorized',
        'forbidden',
        'permission',
        'bad_request',
        'bad-request',
        'not_found',
        'not-found',
        'already_exists',
        'already-exists',
        'duplicate',
        'quota_exceeded',
        'quota-exceeded',
    ];

    /**
     * @var string[]
     */
    private const NON_RETRYABLE_MESSAGE_HINTS = [
        'invalid request',
        'invalid parameter',
        'invalid argument',
        'permission denied',
        'permissions error',
        'forbidden',
        'unauthorized',
        'authentication failed',
        'not found',
        'unknown object',
        'already exists',
        'duplicate entry',
        'duplicate value',
        'conflicting slug',
        'unsupported',
        'missing required',
    ];

    /**
     * @var string[]
     */
    private const RETRYABLE_MESSAGE_HINTS = [
        'timeout',
        'timed out',
        'time out',
        'temporarily unavailable',
        'temporary unavailable',
        'try again later',
        'rate limit',
        'limit exceeded',
        'server error',
        'internal server error',
        'service unavailable',
        'gateway timeout',
        'bad gateway',
        'connection reset',
        'connection refused',
        'connection timed out',
        'could not connect',
        'could not resolve host',
        'ssl connect error',
        'network error',
        'temporarily overloaded',
        'too many connections',
        'mysql server has gone away',
        'deadlock',
        'lock wait timeout',
        'lock-wait timeout',
        'transaction was deadlocked',
        'failed to lock table',
        'resource exhausted',
        'backend error',
        '503',
        '504',
        '502',
        '500',
    ];

    /**
     * @param array{status?: int|string|null, error_code?: string|null} $context
     */
    public static function shouldRetry(Throwable $throwable, array $context = []): bool
    {
        $status = self::resolveStatusCode($throwable, $context['status'] ?? null);
        if ($status !== null) {
            if ($status >= 500) {
                return true;
            }

            if (in_array($status, self::RETRYABLE_SPECIAL_STATUS_CODES, true)) {
                return true;
            }

            if ($status >= 400 && $status < 500) {
                return false;
            }
        }

        $errorCode = strtolower(trim((string) ($context['error_code'] ?? self::extractErrorCode($throwable))));
        if ($errorCode !== '') {
            foreach (self::NON_RETRYABLE_ERROR_CODE_HINTS as $hint) {
                if ($hint !== '' && str_contains($errorCode, $hint)) {
                    return false;
                }
            }

            foreach (self::RETRYABLE_ERROR_CODE_HINTS as $hint) {
                if ($hint !== '' && str_contains($errorCode, $hint)) {
                    return true;
                }
            }
        }

        $message = strtolower(trim($throwable->getMessage()));
        if ($message !== '') {
            foreach (self::NON_RETRYABLE_MESSAGE_HINTS as $hint) {
                if ($hint !== '' && str_contains($message, $hint)) {
                    return false;
                }
            }

            foreach (self::RETRYABLE_MESSAGE_HINTS as $hint) {
                if ($hint !== '' && str_contains($message, $hint)) {
                    return true;
                }
            }
        }

        return false;
    }

    private static function resolveStatusCode(Throwable $throwable, int|string|null $contextStatus): ?int
    {
        if (is_int($contextStatus)) {
            return $contextStatus;
        }

        if (is_numeric($contextStatus)) {
            return (int) $contextStatus;
        }

        $code = $throwable->getCode();
        if (is_int($code) && $code > 0) {
            return $code;
        }

        return null;
    }

    private static function extractErrorCode(Throwable $throwable): ?string
    {
        if (method_exists($throwable, 'errorCode')) {
            /** @var mixed $maybe */
            $maybe = $throwable->errorCode();
            if (is_string($maybe)) {
                return $maybe;
            }
        }

        if (method_exists($throwable, 'reason')) {
            /** @var mixed $maybe */
            $maybe = $throwable->reason();
            if (is_string($maybe)) {
                return $maybe;
            }
        }

        return null;
    }
}
