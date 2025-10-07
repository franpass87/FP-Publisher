<?php

declare(strict_types=1);

namespace FP\Publisher\Support;

final class ContainerRegistry
{
    private static ?Container $instance = null;

    public static function set(Container $container): void
    {
        self::$instance = $container;
    }

    public static function get(): Container
    {
        if (self::$instance === null) {
            self::$instance = new Container();
        }

        return self::$instance;
    }
}


