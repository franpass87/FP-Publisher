<?php

declare(strict_types=1);

namespace FP\Publisher\Support;

use FP\Publisher\Api\Meta\MetaException;
use FP\Publisher\Api\TikTok\TikTokException;
use FP\Publisher\Api\YouTube\YouTubeException;
use FP\Publisher\Api\GoogleBusiness\GoogleBusinessException;
use FP\Publisher\Services\Exceptions\PlanPermissionDenied;
use FP\Publisher\Support\CircuitBreakerOpenException;
use FP\Publisher\Support\Logging\Logger;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

use function __;
use function sprintf;
use function str_contains;
use function strtolower;
use function wp_strip_all_tags;

/**
 * Format exceptions into user-friendly error messages
 * Logs technical details while providing clear messages to users
 */
final class ErrorFormatter
{
    /**
     * Format exception for API response
     *
     * @param Throwable $exception
     * @param string $context Context of the error (e.g., 'plan creation', 'job processing')
     * @return array{message: string, code: string, technical_details?: string}
     */
    public static function format(Throwable $exception, string $context = ''): array
    {
        // Log full technical details for debugging
        Logger::get()->error("Error in {$context}", [
            'exception_class' => get_class($exception),
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => defined('WP_DEBUG') && WP_DEBUG ? $exception->getTraceAsString() : null
        ]);

        // Generate user-friendly message
        $userMessage = self::getUserMessage($exception, $context);
        $errorCode = self::getErrorCode($exception);

        $response = [
            'message' => $userMessage,
            'code' => $errorCode
        ];

        // Include technical details in debug mode
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $response['technical_details'] = wp_strip_all_tags($exception->getMessage());
        }

        return $response;
    }

    /**
     * Get user-friendly error message
     */
    private static function getUserMessage(Throwable $exception, string $context): string
    {
        // Handle specific exception types
        if ($exception instanceof PlanPermissionDenied) {
            return __('You do not have permission to perform this action.', 'fp-publisher');
        }

        if ($exception instanceof CircuitBreakerOpenException) {
            return __('The service is temporarily unavailable due to repeated failures. Please try again in a few minutes.', 'fp-publisher');
        }

        if ($exception instanceof MetaException) {
            return self::formatApiError('Meta (Facebook/Instagram)', $exception);
        }

        if ($exception instanceof TikTokException) {
            return self::formatApiError('TikTok', $exception);
        }

        if ($exception instanceof YouTubeException) {
            return self::formatApiError('YouTube', $exception);
        }

        if ($exception instanceof GoogleBusinessException) {
            return self::formatApiError('Google Business Profile', $exception);
        }

        if ($exception instanceof InvalidArgumentException) {
            // Usually validation errors - message is already user-friendly
            return wp_strip_all_tags($exception->getMessage());
        }

        // Check message content for common patterns
        $message = strtolower($exception->getMessage());

        if (str_contains($message, 'rate limit')) {
            return __('Rate limit reached. Please wait a few minutes and try again.', 'fp-publisher');
        }

        if (str_contains($message, 'timeout') || str_contains($message, 'timed out')) {
            return __('The operation timed out. Please check your connection and try again.', 'fp-publisher');
        }

        if (str_contains($message, 'unauthorized') || str_contains($message, 'authentication')) {
            return __('Authentication failed. Please check your API credentials.', 'fp-publisher');
        }

        if (str_contains($message, 'permission') || str_contains($message, 'forbidden')) {
            return __('Permission denied. Please check your account permissions.', 'fp-publisher');
        }

        if (str_contains($message, 'not found')) {
            return __('The requested resource was not found.', 'fp-publisher');
        }

        if (str_contains($message, 'duplicate')) {
            return __('This content has already been published.', 'fp-publisher');
        }

        if (str_contains($message, 'quota') || str_contains($message, 'limit exceeded')) {
            return __('API quota exceeded. Please try again later.', 'fp-publisher');
        }

        // Generic fallback - don't expose internal details
        if ($context !== '') {
            return sprintf(
                __('An error occurred during %s. Please try again or contact support if the problem persists.', 'fp-publisher'),
                $context
            );
        }

        return __('An unexpected error occurred. Please try again or contact support.', 'fp-publisher');
    }

    /**
     * Format API-specific error
     */
    private static function formatApiError(string $serviceName, Throwable $exception): string
    {
        $message = $exception->getMessage();

        // Check for common API errors
        if (str_contains(strtolower($message), 'token') || str_contains(strtolower($message), 'expired')) {
            return sprintf(
                __('Your %s access token has expired. Please reconnect your account.', 'fp-publisher'),
                $serviceName
            );
        }

        if (str_contains(strtolower($message), 'permission')) {
            return sprintf(
                __('Insufficient permissions for %s. Please check your account settings.', 'fp-publisher'),
                $serviceName
            );
        }

        // Generic API error
        return sprintf(
            __('Unable to publish to %s. Please check your connection settings and try again.', 'fp-publisher'),
            $serviceName
        );
    }

    /**
     * Get error code for categorization
     */
    private static function getErrorCode(Throwable $exception): string
    {
        if ($exception instanceof PlanPermissionDenied) {
            return 'permission_denied';
        }

        if ($exception instanceof CircuitBreakerOpenException) {
            return 'service_unavailable';
        }

        if ($exception instanceof MetaException) {
            return 'meta_api_error';
        }

        if ($exception instanceof TikTokException) {
            return 'tiktok_api_error';
        }

        if ($exception instanceof YouTubeException) {
            return 'youtube_api_error';
        }

        if ($exception instanceof GoogleBusinessException) {
            return 'google_business_api_error';
        }

        if ($exception instanceof InvalidArgumentException) {
            return 'validation_error';
        }

        if ($exception instanceof RuntimeException) {
            $message = strtolower($exception->getMessage());
            
            if (str_contains($message, 'rate limit')) {
                return 'rate_limit_exceeded';
            }
            
            if (str_contains($message, 'timeout')) {
                return 'timeout';
            }
            
            if (str_contains($message, 'database')) {
                return 'database_error';
            }
        }

        return 'internal_error';
    }

    /**
     * Format error for REST API response
     */
    public static function toRestResponse(Throwable $exception, string $context = ''): \WP_REST_Response
    {
        $formatted = self::format($exception, $context);
        
        // Determine HTTP status code
        $httpStatus = match($formatted['code']) {
            'validation_error' => 400,
            'permission_denied' => 403,
            'rate_limit_exceeded' => 429,
            'service_unavailable' => 503,
            'timeout' => 504,
            default => 500
        };

        return new \WP_REST_Response([
            'success' => false,
            'error' => $formatted
        ], $httpStatus);
    }

    /**
     * Get user-friendly message for specific error scenarios
     *
     * @param string $scenario Scenario key (e.g., 'plan_not_found', 'invalid_channel')
     * @param array<string, mixed> $context Additional context for interpolation
     */
    public static function getMessage(string $scenario, array $context = []): string
    {
        return match($scenario) {
            'plan_not_found' => __('The requested plan was not found.', 'fp-publisher'),
            'job_not_found' => __('The requested job was not found.', 'fp-publisher'),
            'invalid_channel' => __('Invalid channel specified.', 'fp-publisher'),
            'invalid_date' => __('Invalid date format. Please use YYYY-MM-DD HH:MM:SS.', 'fp-publisher'),
            'queue_full' => __('The publishing queue is currently full. Please try again later.', 'fp-publisher'),
            'no_tokens' => __('No API tokens configured. Please connect your social media accounts.', 'fp-publisher'),
            'token_expired' => sprintf(
                __('Your %s access token has expired. Please reconnect.', 'fp-publisher'),
                $context['service'] ?? 'API'
            ),
            'network_error' => __('Network error. Please check your internet connection.', 'fp-publisher'),
            'server_error' => __('Server error. Our team has been notified. Please try again later.', 'fp-publisher'),
            default => __('An error occurred. Please try again.', 'fp-publisher')
        };
    }
}
