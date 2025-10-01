<?php

declare(strict_types=1);

namespace FP\Publisher\Tests\Unit\Support;

use FP\Publisher\Support\Security;
use FP\Publisher\Support\Strings;
use PHPUnit\Framework\TestCase;

final class SecurityTest extends TestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();

        Strings::forceMbstringAvailabilityForTesting(null);
    }

    public function testRedactPreservesVisibleCharactersWithMbstring(): void
    {
        Strings::forceMbstringAvailabilityForTesting(true);

        $redacted = Security::redact('áèîõüSecret', 6);

        $this->assertSame('**********Secret', $redacted);
    }

    public function testRedactPreservesVisibleCharactersWithoutMbstring(): void
    {
        Strings::forceMbstringAvailabilityForTesting(false);

        $redacted = Security::redact('áèîõüSecret', 6);

        $this->assertSame('**********Secret', $redacted);
    }
}
