<?php

declare(strict_types=1);

namespace FP\Publisher\Support;

interface ServiceProvider
{
    public function register(Container $container): void;
    public function boot(Container $container): void;
}


