<?php

declare(strict_types=1);

namespace FP\Publisher\Tests\Unit\Support;

use FP\Publisher\Infra\Options;
use FP\Publisher\Support\TermCache;
use PHPUnit\Framework\TestCase;

use function wp_stub_reset;
use function wp_stub_terms_call_count;
use function wp_stub_terms_reset_calls;
use function wp_stub_terms_seed;

final class TermCacheTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        wp_stub_reset();
        Options::bootstrap();
        Options::set('cleanup.terms_cache_ttl_minutes', 90);
    }

    public function testResolvesExistingAndCreatesMissingTermsUsingCache(): void
    {
        $existingId = wp_stub_terms_seed('category', 'News', 321);

        wp_stub_terms_reset_calls();

        $ids = TermCache::resolveIds('category', ['News', 'Features']);

        $this->assertContains($existingId, $ids);
        $this->assertCount(2, $ids);
        $this->assertSame(1, wp_stub_terms_call_count('get_terms'));
        $this->assertSame(1, wp_stub_terms_call_count('wp_insert_term'));

        wp_stub_terms_reset_calls();

        $idsAgain = TermCache::resolveIds('category', ['News', 'Features']);
        $this->assertSame($ids, $idsAgain);
        $this->assertSame(0, wp_stub_terms_call_count('get_terms'));
        $this->assertSame(0, wp_stub_terms_call_count('wp_insert_term'));
    }
}
