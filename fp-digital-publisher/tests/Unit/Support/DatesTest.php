<?php

declare(strict_types=1);

namespace FP\Publisher\Tests\Unit\Support;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use FP\Publisher\Support\Dates;
use PHPUnit\Framework\TestCase;

final class DatesTest extends TestCase
{
    public function testEnsureNormalizesTimezone(): void
    {
        $date = Dates::ensure('2024-01-01 12:00:00', 'UTC');
        $this->assertSame('UTC', $date->getTimezone()->getName());

        $rome = Dates::ensure($date, 'Europe/Rome');
        $this->assertSame('Europe/Rome', $rome->getTimezone()->getName());
        $this->assertSame($date->format('Y-m-d H:i'), $rome->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i'));
    }

    public function testAddAndSubIntervals(): void
    {
        $base = new DateTimeImmutable('2024-01-01 00:00:00', new DateTimeZone('UTC'));

        $added = Dates::add($base, 'P1D');
        $this->assertSame('2024-01-02', $added->format('Y-m-d'));

        $subtracted = Dates::sub($added, new DateInterval('PT2H'));
        $this->assertSame('22', $subtracted->format('H'));
    }

    public function testToUtc(): void
    {
        $paris = new DateTimeImmutable('2024-01-01 12:00:00', new DateTimeZone('Europe/Paris'));
        $utc = Dates::toUtc($paris);

        $this->assertSame('UTC', $utc->getTimezone()->getName());
        $this->assertSame('11:00', $utc->format('H:i'));
    }
}
