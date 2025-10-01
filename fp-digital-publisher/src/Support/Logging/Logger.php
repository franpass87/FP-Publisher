<?php

declare(strict_types=1);

namespace FP\Publisher\Support\Logging;

use Psr\Log\LoggerInterface;

final class Logger
{
    private static ?LoggerInterface $instance = null;

    public static function get(): LoggerInterface
    {
        if (self::$instance === null) {
            self::$instance = LoggerFactory::create();
        }

        return self::$instance;
    }

    public static function set(LoggerInterface $logger): void
    {
        self::$instance = $logger;
    }
}
