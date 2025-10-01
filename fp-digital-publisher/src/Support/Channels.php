<?php

declare(strict_types=1);

namespace FP\Publisher\Support;

use FP\Publisher\Support\Strings;

use function is_string;
use function preg_replace;
use function sanitize_key;
use function trim;

final class Channels
{
    private function __construct()
    {
    }

    public static function normalize(string $channel): string
    {
        $trimmed = trim($channel);
        if ($trimmed === '') {
            return '';
        }

        $underscored = preg_replace('/[\s\-]+/u', '_', $trimmed);
        if (! is_string($underscored)) {
            $underscored = '';
        }

        $normalized = preg_replace('/_+/', '_', $underscored);
        if (! is_string($normalized)) {
            $normalized = '';
        }

        $normalized = trim($normalized, '_');
        if ($normalized === '') {
            return '';
        }

        $sanitized = sanitize_key($normalized);
        if ($sanitized === '') {
            return '';
        }

        $collapsed = preg_replace('/_+/', '_', $sanitized);
        if (! is_string($collapsed)) {
            $collapsed = '';
        }

        $trimmedSanitized = trim($collapsed, '_');
        if ($trimmedSanitized === '') {
            return '';
        }

        $clamped = Strings::trimWidth($trimmedSanitized, 64, '');
        if ($clamped === '') {
            return '';
        }

        $trimmedClamped = trim($clamped, '_');
        if ($trimmedClamped === '') {
            return '';
        }

        return $trimmedClamped;
    }
}
