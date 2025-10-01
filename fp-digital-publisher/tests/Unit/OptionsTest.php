<?php

declare(strict_types=1);

namespace FP\Publisher\Tests\Unit;

use FP\Publisher\Infra\Options;
use FP\Publisher\Support\Channels;
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

    public function testBlackoutWindowChannelsAreClampedToQueueLimit(): void
    {
        $longChannel = 'channel' . str_repeat('abc123', 20);
        Options::set('queue.blackout_windows', [
            [
                'channel' => $longChannel,
                'start' => '09:00',
                'end' => '10:00',
                'timezone' => 'UTC',
            ],
        ]);

        $windows = Options::get('queue.blackout_windows', []);

        $this->assertIsArray($windows);
        $this->assertSame(Channels::normalize($longChannel), $windows[0]['channel']);
    }

    public function testQueueIntegrationChannelConfigurationIsClamped(): void
    {
        $longChannel = 'channel' . str_repeat('xyz789', 20);

        Options::set('integrations.queue.channels', [
            $longChannel => [
                'retry_backoff' => [
                    'base' => 120,
                    'factor' => 2.0,
                    'max' => 600,
                ],
            ],
        ]);

        $channels = Options::get('integrations.queue.channels', []);
        $normalized = Channels::normalize($longChannel);

        $this->assertArrayHasKey($normalized, $channels);
        $this->assertSame(120, $channels[$normalized]['retry_backoff']['base']);
    }

    public function testHttpIntegrationChannelConfigurationIsClamped(): void
    {
        $longChannel = 'channel' . str_repeat('lmn456', 20);

        Options::set('integrations.http.channels', [
            $longChannel => [
                'timeout' => 45,
            ],
        ]);

        $channels = Options::get('integrations.http.channels', []);
        $normalized = Channels::normalize($longChannel);

        $this->assertArrayHasKey($normalized, $channels);
        $this->assertSame(45, $channels[$normalized]['timeout']);
    }

    public function testChannelsOptionNormalizesAndDeduplicatesValues(): void
    {
        $longChannel = 'channel' . str_repeat('rst789', 20);

        Options::set('channels', [
            ' Meta Facebook ',
            'meta-facebook',
            '###',
            $longChannel,
        ]);

        $channels = Options::get('channels', []);

        $this->assertSame([
            Channels::normalize(' Meta Facebook '),
            Channels::normalize($longChannel),
        ], $channels);
    }
}
