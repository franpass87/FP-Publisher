<?php

declare(strict_types=1);

namespace FP\Publisher\Support;

use FP\Publisher\Support\Logging\Logger;
use Throwable;

use function get_transient;
use function set_transient;
use function delete_transient;
use function time;

/**
 * Circuit Breaker pattern implementation
 * Prevents cascading failures by temporarily blocking calls to failing services
 */
final class CircuitBreaker
{
    private const STATE_CLOSED = 'closed';
    private const STATE_OPEN = 'open';
    private const STATE_HALF_OPEN = 'half_open';
    
    private const TRANSIENT_PREFIX = 'fp_pub_cb_';

    private string $service;
    private int $failureThreshold;
    private int $timeoutSeconds;
    private int $retryAfterSeconds;

    /**
     * @param string $service Service identifier (e.g., 'meta_api', 'tiktok_api')
     * @param int $failureThreshold Number of failures before opening circuit
     * @param int $timeoutSeconds How long to keep circuit open
     * @param int $retryAfterSeconds Delay before attempting half-open state
     */
    public function __construct(
        string $service,
        int $failureThreshold = 5,
        int $timeoutSeconds = 60,
        int $retryAfterSeconds = 30
    ) {
        $this->service = $service;
        $this->failureThreshold = max(1, $failureThreshold);
        $this->timeoutSeconds = max(10, $timeoutSeconds);
        $this->retryAfterSeconds = max(5, $retryAfterSeconds);
    }

    /**
     * Execute callable through circuit breaker
     *
     * @template T
     * @param callable(): T $callback
     * @return T
     * @throws CircuitBreakerOpenException
     * @throws Throwable
     */
    public function call(callable $callback)
    {
        $state = $this->getState();

        if ($state === self::STATE_OPEN) {
            if (!$this->shouldAttemptReset()) {
                $this->logCircuitOpen();
                throw new CircuitBreakerOpenException(
                    "Circuit breaker is OPEN for {$this->service}. Service temporarily unavailable."
                );
            }
            
            // Try half-open state
            $this->setState(self::STATE_HALF_OPEN);
            Logger::get()->info("Circuit breaker entering HALF_OPEN state", [
                'service' => $this->service
            ]);
        }

        try {
            $result = $callback();
            $this->onSuccess();
            return $result;
        } catch (Throwable $e) {
            $this->onFailure($e);
            throw $e;
        }
    }

    /**
     * Check if circuit breaker is open
     */
    public function isOpen(): bool
    {
        return $this->getState() === self::STATE_OPEN;
    }

    /**
     * Get current state
     */
    public function getState(): string
    {
        $state = get_transient($this->getStateKey());
        return is_string($state) ? $state : self::STATE_CLOSED;
    }

    /**
     * Get failure count
     */
    public function getFailureCount(): int
    {
        $count = get_transient($this->getFailureCountKey());
        return is_numeric($count) ? (int) $count : 0;
    }

    /**
     * Force reset circuit breaker
     */
    public function reset(): void
    {
        delete_transient($this->getStateKey());
        delete_transient($this->getFailureCountKey());
        delete_transient($this->getOpenedAtKey());
        delete_transient($this->getLastFailureKey());
        
        Logger::get()->info("Circuit breaker manually reset", [
            'service' => $this->service
        ]);
    }

    /**
     * Get circuit breaker statistics
     *
     * @return array{state: string, failures: int, opened_at: int|null, last_failure: string|null}
     */
    public function getStats(): array
    {
        return [
            'state' => $this->getState(),
            'failures' => $this->getFailureCount(),
            'opened_at' => get_transient($this->getOpenedAtKey()) ?: null,
            'last_failure' => get_transient($this->getLastFailureKey()) ?: null
        ];
    }

    /**
     * Handle successful execution
     */
    private function onSuccess(): void
    {
        $previousState = $this->getState();
        
        $this->setState(self::STATE_CLOSED);
        delete_transient($this->getFailureCountKey());
        delete_transient($this->getOpenedAtKey());
        delete_transient($this->getLastFailureKey());

        if ($previousState !== self::STATE_CLOSED) {
            Logger::get()->info("Circuit breaker closed after successful call", [
                'service' => $this->service,
                'previous_state' => $previousState
            ]);
        }
    }

    /**
     * Handle failed execution
     */
    private function onFailure(Throwable $exception): void
    {
        $count = $this->getFailureCount() + 1;
        set_transient($this->getFailureCountKey(), $count, $this->timeoutSeconds);
        set_transient($this->getLastFailureKey(), $exception->getMessage(), $this->timeoutSeconds);

        if ($count >= $this->failureThreshold) {
            $this->openCircuit();
        } else {
            Logger::get()->warning("Circuit breaker failure recorded", [
                'service' => $this->service,
                'failures' => $count,
                'threshold' => $this->failureThreshold,
                'error' => $exception->getMessage()
            ]);
        }
    }

    /**
     * Open the circuit breaker
     */
    private function openCircuit(): void
    {
        $this->setState(self::STATE_OPEN);
        set_transient($this->getOpenedAtKey(), time(), $this->timeoutSeconds);
        
        Logger::get()->critical("Circuit breaker OPENED", [
            'service' => $this->service,
            'failures' => $this->getFailureCount(),
            'threshold' => $this->failureThreshold,
            'timeout_seconds' => $this->timeoutSeconds
        ]);

        // Emit WordPress action for monitoring
        do_action('fp_publisher_circuit_breaker_opened', $this->service, $this->getStats());
    }

    /**
     * Log when circuit is open and blocking calls
     */
    private function logCircuitOpen(): void
    {
        $stats = $this->getStats();
        Logger::get()->warning("Circuit breaker blocking call (OPEN state)", [
            'service' => $this->service,
            'opened_since' => $stats['opened_at'] ? time() - $stats['opened_at'] . 's' : 'unknown',
            'last_failure' => $stats['last_failure']
        ]);
    }

    /**
     * Check if we should attempt to reset circuit
     */
    private function shouldAttemptReset(): bool
    {
        $openedAt = get_transient($this->getOpenedAtKey());
        
        if (!is_numeric($openedAt)) {
            return true;
        }

        $elapsed = time() - (int) $openedAt;
        return $elapsed >= $this->retryAfterSeconds;
    }

    /**
     * Set circuit breaker state
     */
    private function setState(string $state): void
    {
        set_transient($this->getStateKey(), $state, $this->timeoutSeconds);
    }

    /**
     * Get transient key for state
     */
    private function getStateKey(): string
    {
        return self::TRANSIENT_PREFIX . 'state_' . sanitize_key($this->service);
    }

    /**
     * Get transient key for failure count
     */
    private function getFailureCountKey(): string
    {
        return self::TRANSIENT_PREFIX . 'failures_' . sanitize_key($this->service);
    }

    /**
     * Get transient key for opened timestamp
     */
    private function getOpenedAtKey(): string
    {
        return self::TRANSIENT_PREFIX . 'opened_' . sanitize_key($this->service);
    }

    /**
     * Get transient key for last failure message
     */
    private function getLastFailureKey(): string
    {
        return self::TRANSIENT_PREFIX . 'last_failure_' . sanitize_key($this->service);
    }
}

/**
 * Exception thrown when circuit breaker is open
 */
final class CircuitBreakerOpenException extends \RuntimeException
{
    public function __construct(string $message)
    {
        parent::__construct($message, 503); // Service Unavailable
    }
}
