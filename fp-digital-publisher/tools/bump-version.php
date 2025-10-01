#!/usr/bin/env php
<?php

declare(strict_types=1);

$pluginRoot = dirname(__DIR__);

$options = getopt('', ['major', 'minor', 'patch', 'set:', 'file:']);

if ($options === false) {
    fwrite(STDERR, "Unable to parse CLI options.\n");
    exit(1);
}

if (isset($options['set']) && (isset($options['major']) || isset($options['minor']) || isset($options['patch']))) {
    fwrite(STDERR, "--set cannot be combined with --major, --minor or --patch.\n");
    exit(1);
}

$mode = 'patch';
if (isset($options['major'])) {
    $mode = 'major';
} elseif (isset($options['minor'])) {
    $mode = 'minor';
} elseif (isset($options['patch'])) {
    $mode = 'patch';
}

$explicitVersion = $options['set'] ?? null;

$pluginFile = $options['file'] ?? null;
if ($pluginFile !== null) {
    $pluginFile = realpath($pluginFile) ?: $pluginFile;
}

if ($pluginFile === null) {
    $phpFiles = glob($pluginRoot . DIRECTORY_SEPARATOR . '*.php');
    foreach ($phpFiles as $candidate) {
        $contents = file_get_contents($candidate, false, null, 0, 8192);
        if ($contents === false) {
            continue;
        }
        if (preg_match('/^\s*\/\*\*.*?Plugin Name:/ims', $contents) === 1) {
            $pluginFile = $candidate;
            break;
        }
    }
}

if ($pluginFile === null || ! is_file($pluginFile) || ! is_readable($pluginFile)) {
    fwrite(STDERR, "Unable to locate the plugin main file.\n");
    exit(1);
}

$originalContents = file_get_contents($pluginFile);
if ($originalContents === false) {
    fwrite(STDERR, "Unable to read plugin file: {$pluginFile}.\n");
    exit(1);
}

$headerPattern = '/(Version:\s*)([0-9]+\.[0-9]+\.[0-9]+)/i';
if (preg_match($headerPattern, $originalContents, $matches, PREG_OFFSET_CAPTURE) !== 1) {
    fwrite(STDERR, "Version line not found in plugin header.\n");
    exit(1);
}

$currentVersion = $matches[2][0];

if ($explicitVersion !== null) {
    if (! preg_match('/^[0-9]+\.[0-9]+\.[0-9]+$/', $explicitVersion)) {
        fwrite(STDERR, "Invalid version provided via --set.\n");
        exit(1);
    }
    $newVersion = $explicitVersion;
} else {
    $parts = array_map('intval', explode('.', $currentVersion));
    if (count($parts) !== 3) {
        fwrite(STDERR, "Current version is not semantic: {$currentVersion}.\n");
        exit(1);
    }

    switch ($mode) {
        case 'major':
            $parts[0]++;
            $parts[1] = 0;
            $parts[2] = 0;
            break;
        case 'minor':
            $parts[1]++;
            $parts[2] = 0;
            break;
        case 'patch':
        default:
            $parts[2]++;
            break;
    }

    $newVersion = implode('.', $parts);
}

$updated = preg_replace($headerPattern, '${1}' . $newVersion, $originalContents, 1, $replacements);
if ($updated === null || $replacements !== 1) {
    fwrite(STDERR, "Failed to update version header.\n");
    exit(1);
}

$constantPattern = "/(define\\(\\s*'FP_PUBLISHER_VERSION'\\s*,\\s*')([0-9]+\\.[0-9]+\\.[0-9]+)('\\s*\\)\\s*;)/";
$updated = preg_replace($constantPattern, '${1}' . $newVersion . '${3}', $updated, 1);

if (file_put_contents($pluginFile, $updated) === false) {
    fwrite(STDERR, "Unable to write updated plugin file.\n");
    exit(1);
}

echo $newVersion . "\n";
