<?php

declare(strict_types=1);

namespace FP\Publisher\Tests\Unit\Services;

use FP\Publisher\Services\BestTime;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class BestTimeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        wp_stub_reset();
    }

    public function testSuggestionsReturnLocalizedReasons(): void
    {
        $suggestions = BestTime::getSuggestions('Acme', 'facebook', '2024-01');

        $this->assertNotEmpty($suggestions);
        $this->assertSame('High engagement after the weekend', $suggestions[0]['reason']);
    }

    public function testMetaChannelsReuseFacebookRules(): void
    {
        $facebookBase = BestTime::getSuggestions('Acme', 'facebook', '2024-01');
        $instagramBase = BestTime::getSuggestions('Acme', 'instagram', '2024-01');

        $facebook = BestTime::getSuggestions('Acme', 'meta_facebook', '2024-01');
        $instagram = BestTime::getSuggestions('Acme', 'meta_instagram', '2024-01');

        $this->assertSame($facebookBase, $facebook);
        $this->assertSame($instagramBase, $instagram);
    }

    public function testInvalidMonthFormatThrowsTranslatedMessage(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The month must use the YYYY-MM format.');

        BestTime::getSuggestions('Acme', 'facebook', '202401');
    }
}
