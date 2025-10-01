<?php

declare(strict_types=1);

namespace FP\Publisher\Tests\Unit\Api;

use FP\Publisher\Api\Routes;
use FP\Publisher\Domain\PostPlan;
use FP\Publisher\Tests\Fixtures\FakePlansWpdb;
use PHPUnit\Framework\TestCase;
use WP_REST_Request;

use function json_encode;

final class RoutesTest extends TestCase
{
    private FakePlansWpdb $wpdb;

    protected function setUp(): void
    {
        parent::setUp();

        wp_stub_reset();
        $this->wpdb = new FakePlansWpdb();
        global $wpdb;
        $wpdb = $this->wpdb;
    }

    public function testTransitionPlanStatusReturnsForbiddenWhenUserLacksCapability(): void
    {
        $this->wpdb->setPlan([
            'id' => 1,
            'brand' => 'brand',
            'status' => PostPlan::STATUS_READY,
            'approvals_json' => '[]',
            'channel_set_json' => json_encode(['meta']),
            'slots_json' => json_encode([['scheduled_at' => '2024-01-01T00:00:00Z']]),
        ]);

        $GLOBALS['wp_stub_current_user_id'] = 123;
        $GLOBALS['wp_stub_current_user_caps'] = [];

        $request = new WP_REST_Request([
            'id' => 1,
            'status' => PostPlan::STATUS_APPROVED,
        ]);

        $response = Routes::transitionPlanStatus($request);

        $this->assertInstanceOf(\WP_Error::class, $response);
        $this->assertSame('fp_publisher_transition_forbidden', $response->get_error_code());
        $this->assertSame(403, $response->get_error_data()['status'] ?? null);
    }

    public function testLoadPlanFromDbReturnsStoredAssetsAndTemplate(): void
    {
        $assets = [['id' => 5]];
        $template = [
            'id' => 3,
            'name' => 'My Template',
            'body' => 'Body',
            'placeholders' => ['title'],
            'channel_overrides' => [],
        ];

        $this->wpdb->setPlan([
            'id' => 10,
            'brand' => 'brand',
            'status' => PostPlan::STATUS_READY,
            'approvals_json' => '[]',
            'channel_set_json' => json_encode(['meta']),
            'slots_json' => json_encode([['scheduled_at' => '2024-01-01T00:00:00Z']]),
            'assets_json' => json_encode($assets),
            'template_json' => json_encode($template),
        ]);

        $method = new \ReflectionMethod(Routes::class, 'loadPlanFromDb');
        $method->setAccessible(true);

        $plan = $method->invoke(null, 10);

        $this->assertNotNull($plan);
        $this->assertSame($assets, $plan['assets']);
        $this->assertSame($template['id'], $plan['template']['id']);
        $this->assertSame($template['name'], $plan['template']['name']);
    }

    public function testVerifyNonceAcceptsGracePeriodReturnValue(): void
    {
        $request = new WP_REST_Request();
        $request->set_param('_wpnonce', 'nonce');

        \wp_stub_set_nonce_result(2);

        $method = new \ReflectionMethod(Routes::class, 'verifyNonce');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke(null, $request));

        \wp_stub_set_nonce_result(0);
        $this->assertFalse($method->invoke(null, $request));
    }
}
