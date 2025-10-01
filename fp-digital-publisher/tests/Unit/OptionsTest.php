<?php

declare(strict_types=1);

namespace FP\Publisher\Tests\Unit;

use FP\Publisher\Infra\Options;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class OptionsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        wp_stub_reset();
    }

    public function testBootstrapAddsOptionWithAutoloadDisabled(): void
    {
        Options::bootstrap();

        $this->assertSame('no', $GLOBALS['wp_stub_options_autoload']['fp_publisher_options'] ?? null);
        $this->assertIsArray($GLOBALS['wp_stub_options']['fp_publisher_options'] ?? null);
    }

    public function testBootstrapUpdatesAutoloadForExistingOption(): void
    {
        $GLOBALS['wp_stub_options']['fp_publisher_options'] = ['foo' => 'bar'];
        $GLOBALS['wp_stub_options_autoload']['fp_publisher_options'] = 'yes';

        Options::bootstrap();

        $this->assertSame('no', $GLOBALS['wp_stub_options_autoload']['fp_publisher_options'] ?? null);
        $this->assertSame(['foo' => 'bar'], $GLOBALS['wp_stub_options']['fp_publisher_options']);
    }

    public function testSanitizeTimezoneFallsBackToDefaultWhenInvalid(): void
    {
        Options::set('timezone', 'Invalid/Zone');

        $this->assertSame('Europe/Rome', Options::get('timezone'));
    }

    public function testSetTokenSanitizesServiceKey(): void
    {
        Options::setToken('Service Name', 'token');

        $tokens = Options::getTokens();
        $this->assertArrayHasKey('service_name', $tokens);
    }

    public function testSetTokenThrowsWhenServiceKeyIsInvalid(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid token service identifier.');

        Options::setToken('   ', 'token');
    }

    public function testSetThrowsWhenUpdateFails(): void
    {
        wp_stub_set_update_option_failure('fp_publisher_options', true);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unable to persist configuration changes.');

        Options::set('timezone', 'UTC');
    }

    public function testSetTokenThrowsWhenUpdateFails(): void
    {
        wp_stub_set_update_option_failure('fp_publisher_options', true);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unable to persist token changes.');

        Options::setToken('meta', 'token');
    }

    public function testBlackoutWindowTimezoneFallsBackToPluginSetting(): void
    {
        Options::set('timezone', 'UTC');

        Options::set('queue.blackout_windows', [
            [
                'channel' => 'meta_facebook',
                'start' => '09:00',
                'end' => '10:00',
                'timezone' => 'Invalid/Zone',
            ],
        ]);

        $windows = Options::get('queue.blackout_windows', []);

        $this->assertIsArray($windows);
        $this->assertSame('UTC', $windows[0]['timezone']);
    }
}
