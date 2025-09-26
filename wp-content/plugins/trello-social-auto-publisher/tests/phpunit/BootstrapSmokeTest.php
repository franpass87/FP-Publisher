<?php
declare(strict_types=1);

namespace TSAP\Tests\PHPUnit;

use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class BootstrapSmokeTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        $pluginRoot = dirname(__DIR__, 2);
        require_once $pluginRoot . '/trello-social-auto-publisher.php';

        if (!defined('WP_DEBUG')) {
            define('WP_DEBUG', false);
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        if (function_exists('tts_reset_test_state')) {
            tts_reset_test_state();
        }

        $this->resetBootstrapSingleton();
        unset($GLOBALS['tsap_runtime_logger']);
    }

    public function testRegisterHooksRegistersExpectedActions(): void
    {
        \TTS_Plugin_Bootstrap::instance()->register();

        $hooks = array_map(
            static fn(array $registration): string => $registration[0],
            $GLOBALS['tts_registered_actions'] ?? []
        );

        $this->assertContains('plugins_loaded', $hooks);
        $this->assertContains('admin_init', $hooks);
        $this->assertContains('admin_notices', $hooks);
    }

    public function testContainerReturnsSingletonInstance(): void
    {
        $first = \TTS_Plugin_Bootstrap::instance()->container();
        $second = \TTS_Plugin_Bootstrap::instance()->container();

        $this->assertSame($first, $second);
    }

    public function testRuntimeLoggerRemainsDisabledWhenDebugFalse(): void
    {
        \TTS_Plugin_Bootstrap::instance()->boot_runtime_logger();

        $this->assertArrayNotHasKey('tsap_runtime_logger', $GLOBALS);
    }

    public function testRuntimeLoggerCanBeEnabledViaFilter(): void
    {
        add_filter('tsap_enable_runtime_logger', static fn(bool $enabled): bool => true, 10, 1);

        \TTS_Plugin_Bootstrap::instance()->boot_runtime_logger();

        $this->assertArrayHasKey('tsap_runtime_logger', $GLOBALS);
        $this->assertInstanceOf(\TTS_Runtime_Logger::class, $GLOBALS['tsap_runtime_logger']);
    }

    /**
     * Reset the bootstrap singleton between tests to avoid shared state.
     */
    private function resetBootstrapSingleton(): void
    {
        $reflection = new ReflectionClass(\TTS_Plugin_Bootstrap::class);

        if ($reflection->hasProperty('instance')) {
            $property = $reflection->getProperty('instance');
            $property->setAccessible(true);
            $property->setValue(null, null);
        }
    }
}
