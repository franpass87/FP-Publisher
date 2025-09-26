<?php
declare(strict_types=1);

namespace TSAP\Tests\PHPUnit;

use PHPUnit\Framework\TestCase;

final class LegacyScriptsTest extends TestCase
{
    /**
     * @dataProvider legacyScriptProvider
     */
    public function testLegacyScriptsExecuteWithoutFailures(string $relativePath): void
    {
        $pluginRoot = dirname(__DIR__, 2);
        $scriptPath = $pluginRoot . '/tests/' . $relativePath;

        $this->assertFileExists($scriptPath);

        $commandParts = [escapeshellarg(PHP_BINARY)];

        if (PHP_SAPI === 'phpdbg') {
            $commandParts[] = '-qrr';
        }

        $commandParts[] = escapeshellarg($scriptPath);
        $command = implode(' ', $commandParts);

        exec($command . ' 2>&1', $output, $exitCode);

        $message = sprintf(
            "Legacy test %s failed with exit code %d.\n%s",
            $relativePath,
            $exitCode,
            implode(PHP_EOL, $output)
        );

        $this->assertSame(0, $exitCode, $message);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function legacyScriptProvider(): array
    {
        $pluginRoot = dirname(__DIR__, 2);
        $testsDir = $pluginRoot . '/tests';

        if (!is_dir($testsDir)) {
            return [];
        }

        $files = glob($testsDir . '/test-*.php') ?: [];
        sort($files);

        $cases = [];
        foreach ($files as $file) {
            $cases[basename($file)] = [basename($file)];
        }

        return $cases;
    }
}
