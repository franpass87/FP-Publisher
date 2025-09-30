<?php

declare(strict_types=1);

namespace FP\Publisher\Tests\Unit\Support;

use FP\Publisher\Support\Validation;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ValidationTest extends TestCase
{
    public function testGuardWrapsUnexpectedExceptions(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('boom');

        Validation::guard(static function (): void {
            throw new RuntimeException('boom');
        });
    }

    public function testStringValidation(): void
    {
        $this->assertSame('value', Validation::string('value', 'field'));

        $this->expectException(InvalidArgumentException::class);
        Validation::string('   ', 'field');
    }

    public function testNullableStringAllowsNull(): void
    {
        $this->assertNull(Validation::nullableString(null, 'field'));
        $this->assertSame('test', Validation::nullableString('test', 'field'));
    }

    public function testPositiveIntValidation(): void
    {
        $this->assertSame(10, Validation::positiveInt(10, 'field'));

        $this->expectException(InvalidArgumentException::class);
        Validation::positiveInt(-1, 'field');
    }

    public function testArrayOfStrings(): void
    {
        $values = Validation::arrayOfStrings(['a', 'b'], 'items');
        $this->assertSame(['a', 'b'], $values);
    }
}
