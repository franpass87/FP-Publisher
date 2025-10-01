<?php

declare(strict_types=1);

namespace FP\Publisher\Support\Logging;

use Psr\Log\LoggerInterface;

final class LoggerFactory
{
    public static function create(): LoggerInterface
    {
        return new StructuredLogger();
    }
}
