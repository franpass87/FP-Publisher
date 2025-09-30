<?php

declare(strict_types=1);

namespace FP\Publisher\Tests\WpIntegration;

use FP\Publisher\Services\Links;
use WP_UnitTestCase;

require_once __DIR__ . '/bootstrap.php';

final class RewriteTest extends WP_UnitTestCase
{
    protected function setUp(): void
    {
        if (defined('FP_PUBLISHER_SKIP_WP_TESTS') && FP_PUBLISHER_SKIP_WP_TESTS) {
            $this->markTestSkipped('WordPress test suite not available.');
        }

        parent::setUp();
    }

    public function testQueryVarAndRewriteAreRegistered(): void
    {
        global $wp_rewrite;

        $vars = apply_filters('query_vars', []);
        $this->assertContains(Links::QUERY_VAR, $vars);

        $wp_rewrite->init();
        do_action('init');
        flush_rewrite_rules(false);

        $rules = get_option('rewrite_rules');
        $this->assertIsArray($rules);

        $matched = false;
        foreach ($rules as $regex => $query) {
            if (str_contains($regex, '^go/([^/]+)/?$') && str_contains($query, Links::QUERY_VAR)) {
                $matched = true;
                break;
            }
        }

        $this->assertTrue($matched, 'Expected go/ rewrite rule to be registered.');
    }
}
