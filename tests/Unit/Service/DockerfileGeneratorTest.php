<?php

declare(strict_types=1);

// ABOUTME: Tests for DockerfileGenerator service.
// ABOUTME: Validates Dockerfile generation for different server types.

namespace Seaman\Tests\Unit\Service;

use Seaman\Service\DockerfileGenerator;
use Seaman\Service\TemplateRenderer;
use Seaman\ValueObject\ServerConfig;
use Seaman\ValueObject\PhpConfig;
use Seaman\ValueObject\XdebugConfig;

beforeEach(function () {
    $templateDir = __DIR__ . '/../../../src/Template';
    $this->renderer = new TemplateRenderer($templateDir);
    $this->generator = new DockerfileGenerator($this->renderer);
});

test('generates Dockerfile for Symfony server', function () {
    $server = new ServerConfig('symfony', 8000);
    $xdebug = new XdebugConfig(false, 'PHPSTORM', 'host.docker.internal');
    $php = new PhpConfig('8.4', ['pdo_pgsql', 'redis'], $xdebug);

    $dockerfile = $this->generator->generate($server, $php);

    expect($dockerfile)->toContain('FROM php:8.4-cli-alpine')
        ->and($dockerfile)->toContain('symfony server:start')
        ->and($dockerfile)->toContain('pdo_pgsql')
        ->and($dockerfile)->toContain('redis');
});

test('generates Dockerfile for Nginx + FPM server', function () {
    $server = new ServerConfig('nginx-fpm', 8000);
    $xdebug = new XdebugConfig(false, 'PHPSTORM', 'host.docker.internal');
    $php = new PhpConfig('8.4', ['pdo_pgsql'], $xdebug);

    $dockerfile = $this->generator->generate($server, $php);

    expect($dockerfile)->toContain('FROM php:8.4-fpm-alpine')
        ->and($dockerfile)->toContain('nginx')
        ->and($dockerfile)->toContain('php-fpm');
});

test('generates Dockerfile for FrankenPHP server', function () {
    $server = new ServerConfig('frankenphp', 8000);
    $xdebug = new XdebugConfig(false, 'PHPSTORM', 'host.docker.internal');
    $php = new PhpConfig('8.4', [], $xdebug);

    $dockerfile = $this->generator->generate($server, $php);

    expect($dockerfile)->toContain('FROM dunglas/frankenphp')
        ->and($dockerfile)->toContain('frankenphp run');
});
