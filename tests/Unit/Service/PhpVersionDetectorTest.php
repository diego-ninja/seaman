<?php

declare(strict_types=1);

namespace Seaman\Tests\Unit\Service;

use Seaman\Enum\PhpVersion;
use Seaman\Service\PhpVersionDetector;

beforeEach(function () {
    $this->testDir = sys_get_temp_dir() . '/seaman-test-' . uniqid();
    mkdir($this->testDir);
});

afterEach(function () {
    if (isset($this->testDir) && is_dir($this->testDir)) {
        exec("rm -rf {$this->testDir}");
    }
});

test('detects PHP version from composer.json with caret constraint', function () {
    $composerJson = [
        'require' => [
            'php' => '^8.4',
        ],
    ];
    file_put_contents($this->testDir . '/composer.json', json_encode($composerJson));

    $detector = new PhpVersionDetector();
    $version = $detector->detectFromComposer($this->testDir);

    expect($version)->toBe(PhpVersion::Php84);
});

test('detects PHP version from composer.json with greater than constraint', function () {
    $composerJson = [
        'require' => [
            'php' => '>=8.3',
        ],
    ];
    file_put_contents($this->testDir . '/composer.json', json_encode($composerJson));

    $detector = new PhpVersionDetector();
    $version = $detector->detectFromComposer($this->testDir);

    expect($version)->toBe(PhpVersion::Php83);
});

test('detects PHP version from composer.json with tilde constraint', function () {
    $composerJson = [
        'require' => [
            'php' => '~8.4.0',
        ],
    ];
    file_put_contents($this->testDir . '/composer.json', json_encode($composerJson));

    $detector = new PhpVersionDetector();
    $version = $detector->detectFromComposer($this->testDir);

    expect($version)->toBe(PhpVersion::Php84);
});

test('returns null when composer.json does not exist', function () {
    $detector = new PhpVersionDetector();
    $version = $detector->detectFromComposer($this->testDir);

    expect($version)->toBeNull();
});

test('returns null when composer.json has no php requirement', function () {
    $composerJson = [
        'require' => [
            'symfony/console' => '^7.0',
        ],
    ];
    file_put_contents($this->testDir . '/composer.json', json_encode($composerJson));

    $detector = new PhpVersionDetector();
    $version = $detector->detectFromComposer($this->testDir);

    expect($version)->toBeNull();
});

test('returns null when PHP version is not supported', function () {
    $composerJson = [
        'require' => [
            'php' => '^7.4',
        ],
    ];
    file_put_contents($this->testDir . '/composer.json', json_encode($composerJson));

    $detector = new PhpVersionDetector();
    $version = $detector->detectFromComposer($this->testDir);

    expect($version)->toBeNull();
});

test('returns null when composer.json is invalid JSON', function () {
    file_put_contents($this->testDir . '/composer.json', '{invalid json}');

    $detector = new PhpVersionDetector();
    $version = $detector->detectFromComposer($this->testDir);

    expect($version)->toBeNull();
});

test('detect method uses detectFromComposer as primary source', function () {
    $composerJson = [
        'require' => [
            'php' => '^8.4',
        ],
    ];
    file_put_contents($this->testDir . '/composer.json', json_encode($composerJson));

    $detector = new PhpVersionDetector();
    $version = $detector->detect($this->testDir);

    expect($version)->toBe(PhpVersion::Php84);
});
