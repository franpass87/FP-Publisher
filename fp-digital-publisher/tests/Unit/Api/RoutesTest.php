<?php

declare(strict_types=1);

namespace FP\Publisher\Tests\Unit\Api;

use FP\Publisher\Api\Routes;
use FP\Publisher\Domain\PostPlan;
use FP\Publisher\Support\Channels;
use FP\Publisher\Tests\Fixtures\FakePlansWpdb;
use PHPUnit\Framework\TestCase;
use WP_REST_Request;

use function json_encode;
use function strlen;
use function str_repeat;
use function wp_stub_register_user;
use function wp_stub_rest_reset;
use function wp_stub_rest_routes;

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

    public function testAuthorizeSkipsNonceForAuthorizationHeader(): void
    {
        $GLOBALS['wp_stub_current_user_id'] = 99;
        $GLOBALS['wp_stub_current_user_caps'] = ['manage_options' => true];

        $request = new WP_REST_Request();
        $request->set_header('Authorization', 'Basic Zm9v');

        $method = new \ReflectionMethod(Routes::class, 'authorize');
        $method->setAccessible(true);

        $result = $method->invoke(null, $request, 'manage_options');

        $this->assertTrue($result);
    }

    public function testAuthorizeRequiresNonceWhenUsingCookies(): void
    {
        $GLOBALS['wp_stub_current_user_id'] = 101;
        $GLOBALS['wp_stub_current_user_caps'] = ['manage_options' => true];

        $request = new WP_REST_Request();

        $method = new \ReflectionMethod(Routes::class, 'authorize');
        $method->setAccessible(true);

        $result = $method->invoke(null, $request, 'manage_options');

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('fp_publisher_invalid_nonce', $result->get_error_code());
    }

    public function testGetBestTimeClampsChannelName(): void
    {
        $request = new WP_REST_Request();
        $request->set_param('brand', 'Brand');
        $longChannel = 'channel' . str_repeat('abc123', 20);
        $request->set_param('channel', $longChannel);
        $request->set_param('month', '2024-01');

        $response = Routes::getBestTime($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
        $data = $response->get_data();
        $expected = Channels::normalize($longChannel);
        $this->assertSame($expected, $data['channel']);
        $this->assertLessThanOrEqual(64, strlen($expected));
    }

    public function testListPlansFiltersByBrandChannelAndMonth(): void
    {
        $template = [
            'id' => 1,
            'name' => 'Template',
            'body' => 'Body',
            'placeholders' => [],
            'channel_overrides' => [],
        ];

        $this->wpdb->setPlan([
            'id' => 1,
            'brand' => 'alpha',
            'status' => PostPlan::STATUS_READY,
            'channel_set_json' => json_encode(['instagram']),
            'slots_json' => json_encode([
                ['channel' => 'instagram', 'scheduled_at' => '2024-01-15T10:00:00+01:00'],
            ]),
            'assets_json' => json_encode([]),
            'template_json' => json_encode($template),
            'created_at' => '2023-12-20 10:00:00',
            'updated_at' => '2023-12-21 11:00:00',
        ]);

        $this->wpdb->setPlan([
            'id' => 2,
            'brand' => 'beta',
            'status' => PostPlan::STATUS_READY,
            'channel_set_json' => json_encode(['tiktok']),
            'slots_json' => json_encode([
                ['channel' => 'tiktok', 'scheduled_at' => '2024-02-01T08:00:00+01:00'],
            ]),
            'assets_json' => json_encode([]),
            'template_json' => json_encode($template),
            'created_at' => '2024-01-02 09:00:00',
            'updated_at' => '2024-01-03 09:30:00',
        ]);

        $request = new WP_REST_Request();
        $request->set_param('brand', 'alpha');
        $request->set_param('channel', 'instagram');
        $request->set_param('month', '2024-01');

        $response = Routes::listPlans($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
        $data = $response->get_data();
        $this->assertArrayHasKey('items', $data);
        $this->assertCount(1, $data['items']);
        $this->assertSame(1, $data['items'][0]['id']);
        $this->assertSame('alpha', $data['items'][0]['brand']);
        $this->assertSame(PostPlan::STATUS_READY, $data['items'][0]['status']);
        $this->assertSame(1, $data['total']);
        $this->assertSame(1, $data['page']);
        $this->assertSame(20, $data['per_page']);
    }

    public function testListPlansSupportsPagination(): void
    {
        $template = [
            'id' => 2,
            'name' => 'Template',
            'body' => 'Body',
            'placeholders' => [],
            'channel_overrides' => [],
        ];

        $this->wpdb->setPlan([
            'id' => 1,
            'brand' => 'alpha',
            'status' => PostPlan::STATUS_READY,
            'channel_set_json' => json_encode(['instagram']),
            'slots_json' => json_encode([
                ['channel' => 'instagram', 'scheduled_at' => '2024-01-05T08:00:00+00:00'],
            ]),
            'assets_json' => json_encode([]),
            'template_json' => json_encode($template),
            'created_at' => '2023-12-01 08:00:00',
            'updated_at' => '2023-12-02 08:00:00',
        ]);

        $this->wpdb->setPlan([
            'id' => 2,
            'brand' => 'alpha',
            'status' => PostPlan::STATUS_READY,
            'channel_set_json' => json_encode(['instagram']),
            'slots_json' => json_encode([
                ['channel' => 'instagram', 'scheduled_at' => '2024-02-05T08:00:00+00:00'],
            ]),
            'assets_json' => json_encode([]),
            'template_json' => json_encode($template),
            'created_at' => '2023-12-15 08:00:00',
            'updated_at' => '2023-12-16 08:00:00',
        ]);

        $this->wpdb->setPlan([
            'id' => 3,
            'brand' => 'alpha',
            'status' => PostPlan::STATUS_READY,
            'channel_set_json' => json_encode(['instagram']),
            'slots_json' => json_encode([
                ['channel' => 'instagram', 'scheduled_at' => '2024-03-05T08:00:00+00:00'],
            ]),
            'assets_json' => json_encode([]),
            'template_json' => json_encode($template),
            'created_at' => '2024-01-05 08:00:00',
            'updated_at' => '2024-01-06 08:00:00',
        ]);

        $request = new WP_REST_Request();
        $request->set_param('brand', 'alpha');
        $request->set_param('channel', 'instagram');
        $request->set_param('page', 2);
        $request->set_param('per_page', 2);

        $response = Routes::listPlans($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
        $data = $response->get_data();
        $this->assertSame(3, $data['total']);
        $this->assertSame(2, $data['page']);
        $this->assertSame(2, $data['per_page']);
        $this->assertCount(1, $data['items']);
        $this->assertSame(3, $data['items'][0]['id']);
    }

    public function testGetPlanApprovalsReturnsTimeline(): void
    {
        $this->wpdb->setPlan([
            'id' => 5,
            'brand' => 'alpha',
            'status' => PostPlan::STATUS_READY,
            'approvals_json' => json_encode([
                [
                    'user_id' => 7,
                    'from' => PostPlan::STATUS_DRAFT,
                    'to' => PostPlan::STATUS_READY,
                    'at' => '2024-01-10T09:00:00+00:00',
                ],
            ]),
            'channel_set_json' => json_encode(['instagram']),
            'slots_json' => json_encode([
                ['channel' => 'instagram', 'scheduled_at' => '2024-01-20T10:00:00+00:00'],
            ]),
            'assets_json' => json_encode([]),
            'template_json' => json_encode([
                'id' => 1,
                'name' => 'Template',
                'body' => 'Body',
                'placeholders' => [],
                'channel_overrides' => [],
            ]),
            'created_at' => '2024-01-01 08:00:00',
            'updated_at' => '2024-01-10 09:00:00',
        ]);

        wp_stub_register_user([
            'ID' => 7,
            'user_login' => 'reviewer',
            'user_nicename' => 'reviewer',
            'display_name' => 'Jane Reviewer',
            'user_email' => 'reviewer@example.com',
        ]);

        $request = new WP_REST_Request();
        $request->set_param('id', 5);

        $response = Routes::getPlanApprovals($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
        $data = $response->get_data();

        $this->assertSame(5, $data['plan_id']);
        $this->assertSame(PostPlan::STATUS_READY, $data['status']);
        $this->assertCount(1, $data['items']);
        $this->assertSame(PostPlan::STATUS_READY, $data['items'][0]['status']);
        $this->assertSame('Jane Reviewer', $data['items'][0]['actor']['display_name']);
    }

    public function testRegisterCrudRoutesSkipsPostWhenCallbackMissing(): void
    {
        wp_stub_rest_reset();

        $method = new \ReflectionMethod(Routes::class, 'registerCrudRoutes');
        $method->setAccessible(true);

        $method->invoke(null, 'accounts', 'fp_publisher_manage_accounts');

        $routes = wp_stub_rest_routes();
        $this->assertCount(1, $routes);
        $this->assertSame('fp-publisher/v1', $routes[0]['namespace']);
        $this->assertSame('/accounts', $routes[0]['route']);
        $this->assertCount(1, $routes[0]['args']);
        $this->assertSame('GET', $routes[0]['args'][0]['methods']);
    }

    public function testRegisterCrudRoutesIncludesPostWhenCallbackProvided(): void
    {
        wp_stub_rest_reset();

        $method = new \ReflectionMethod(Routes::class, 'registerCrudRoutes');
        $method->setAccessible(true);

        $method->invoke(
            null,
            'links',
            'fp_publisher_manage_links',
            [Routes::class, 'getLinks'],
            [Routes::class, 'saveLink']
        );

        $routes = wp_stub_rest_routes();
        $this->assertCount(1, $routes);
        $this->assertSame('/links', $routes[0]['route']);
        $this->assertCount(2, $routes[0]['args']);
        $this->assertSame('POST', $routes[0]['args'][1]['methods']);
    }
}
