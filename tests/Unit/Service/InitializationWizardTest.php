<?php

declare(strict_types=1);

namespace Seaman\Tests\Unit\Service;

use Seaman\Enum\PhpVersion;
use Seaman\Enum\ProjectType;
use Seaman\Enum\Service;
use Seaman\Service\InitializationWizard;
use Seaman\Service\PhpVersionDetector;

test('get default services returns correct services for WebApplication', function () {
    $detector = new PhpVersionDetector();
    $wizard = new InitializationWizard($detector);

    $defaults = $wizard->getDefaultServices(ProjectType::WebApplication);

    expect($defaults)->toBe([Service::Redis, Service::Mailpit]);
});

test('get default services returns correct services for ApiPlatform', function () {
    $detector = new PhpVersionDetector();
    $wizard = new InitializationWizard($detector);

    $defaults = $wizard->getDefaultServices(ProjectType::ApiPlatform);

    expect($defaults)->toBe([Service::Redis]);
});

test('get default services returns correct services for Microservice', function () {
    $detector = new PhpVersionDetector();
    $wizard = new InitializationWizard($detector);

    $defaults = $wizard->getDefaultServices(ProjectType::Microservice);

    expect($defaults)->toBe([Service::Redis]);
});

test('get default services returns empty array for Skeleton', function () {
    $detector = new PhpVersionDetector();
    $wizard = new InitializationWizard($detector);

    $defaults = $wizard->getDefaultServices(ProjectType::Skeleton);

    expect($defaults)->toBeEmpty();
});

test('get default services returns empty array for Existing', function () {
    $detector = new PhpVersionDetector();
    $wizard = new InitializationWizard($detector);

    $defaults = $wizard->getDefaultServices(ProjectType::Existing);

    expect($defaults)->toBeEmpty();
});

test('detects PHP version from project root', function () {
    $testDir = sys_get_temp_dir() . '/seaman-wizard-test-' . uniqid();
    mkdir($testDir);

    $composerJson = ['require' => ['php' => '^8.4']];
    file_put_contents($testDir . '/composer.json', json_encode($composerJson));

    $detector = new PhpVersionDetector();
    $wizard = new InitializationWizard($detector);

    $version = $wizard->detectPhpVersion($testDir);

    expect($version)->toBe(PhpVersion::Php84);

    exec("rm -rf {$testDir}");
});

test('detect PHP version returns null when no composer.json exists', function () {
    $testDir = sys_get_temp_dir() . '/seaman-wizard-test-' . uniqid();
    mkdir($testDir);

    $detector = new PhpVersionDetector();
    $wizard = new InitializationWizard($detector);

    $version = $wizard->detectPhpVersion($testDir);

    expect($version)->toBeNull();

    exec("rm -rf {$testDir}");
});

test('get project name returns basename when directory is empty', function () {
    $testDir = sys_get_temp_dir() . '/my-project-' . uniqid();
    mkdir($testDir);

    $detector = new PhpVersionDetector();
    $wizard = new InitializationWizard($detector);

    $name = $wizard->getProjectName($testDir);

    expect($name)->toStartWith('my-project-');

    exec("rm -rf {$testDir}");
});

test('get project name returns default when directory is not empty', function () {
    $testDir = sys_get_temp_dir() . '/my-project-' . uniqid();
    mkdir($testDir);
    file_put_contents($testDir . '/composer.json', '{}');

    $detector = new PhpVersionDetector();
    $wizard = new InitializationWizard($detector);

    $name = $wizard->getProjectName($testDir);

    expect($name)->toBe('symfony-app');

    exec("rm -rf {$testDir}");
});
