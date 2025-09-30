<?php

declare(strict_types=1);

namespace FP\Publisher\Tests\WpIntegration;

use FP\Publisher\Infra\Capabilities;
use WP_Role;
use WP_UnitTestCase;

require_once __DIR__ . '/bootstrap.php';

final class CapabilitiesTest extends WP_UnitTestCase
{
    protected function setUp(): void
    {
        if (defined('FP_PUBLISHER_SKIP_WP_TESTS') && FP_PUBLISHER_SKIP_WP_TESTS) {
            $this->markTestSkipped('WordPress test suite not available.');
        }

        parent::setUp();
    }

    public function testRolesAreCreatedWithCapabilities(): void
    {
        do_action('init');

        $admin = get_role(Capabilities::ROLE_ADMIN);
        $editor = get_role(Capabilities::ROLE_EDITOR);
        $wpAdmin = get_role('administrator');

        $this->assertInstanceOf(WP_Role::class, $admin);
        $this->assertTrue($admin->has_cap('fp_publisher_manage_settings'));

        $this->assertInstanceOf(WP_Role::class, $editor);
        $this->assertTrue($editor->has_cap('fp_publisher_manage_plans'));
        $this->assertFalse($editor->has_cap('fp_publisher_manage_settings'));

        $this->assertInstanceOf(WP_Role::class, $wpAdmin);
        $this->assertTrue($wpAdmin->has_cap('fp_publisher_manage_settings'));
    }
}
