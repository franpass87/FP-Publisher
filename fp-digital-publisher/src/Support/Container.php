<?php

declare(strict_types=1);

namespace FP\Publisher\Support;

/**
 * Minimalistic service container for wiring dependencies without global state.
 *
 * This is intentionally small and framework-agnostic to avoid adding heavy
 * runtime requirements. It supports:
 * - binding concrete factories (closures) or instances
 * - lazy resolution with singleton semantics per service id
 * - simple has/get API used by Service Providers and the Loader
 */
final class Container
{
    /**
     * @var array<string, callable(self): mixed>
     */
    private array $factories = [];

    /**
     * @var array<string, mixed>
     */
    private array $singletons = [];

    /**
     * Register a service factory. The factory will be invoked once on first use.
     */
    public function bind(string $id, callable $factory): void
    {
        unset($this->singletons[$id]);
        $this->factories[$id] = $factory;
    }

    /**
     * Register a pre-built singleton instance.
     *
     * @param mixed $instance
     */
    public function instance(string $id, $instance): void
    {
        $this->singletons[$id] = $instance;
        unset($this->factories[$id]);
    }

    /**
     * Resolve a service by id.
     *
     * @return mixed
     */
    public function get(string $id)
    {
        if (array_key_exists($id, $this->singletons)) {
            return $this->singletons[$id];
        }

        if (! array_key_exists($id, $this->factories)) {
            throw new \RuntimeException('Service not found: ' . $id);
        }

        $service = ($this->factories[$id])($this);
        $this->singletons[$id] = $service;
        unset($this->factories[$id]);

        return $service;
    }

    public function has(string $id): bool
    {
        return array_key_exists($id, $this->singletons) || array_key_exists($id, $this->factories);
    }
}


