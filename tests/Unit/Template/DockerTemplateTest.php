<?php

// ABOUTME: Tests for Dockerfile.twig template rendering.
// ABOUTME: Validates correct output for each server type.

declare(strict_types=1);

use Seaman\Service\TemplateRenderer;

beforeEach(function (): void {
    $this->renderer = new TemplateRenderer(__DIR__ . '/../../../src/Template');
});

describe('Dockerfile.twig', function (): void {
    test('renders symfony server dockerfile', function (): void {
        $content = $this->renderer->render('docker/Dockerfile.twig', [
            'server' => 'symfony',
            'php_version' => '8.4',
        ]);

        expect($content)
            ->toContain('FROM ubuntu:24.04')
            ->toContain('"symfony", "server:start"')
            ->not->toContain('dunglas/frankenphp');
    });

    test('renders frankenphp classic dockerfile', function (): void {
        $content = $this->renderer->render('docker/Dockerfile.twig', [
            'server' => 'frankenphp',
            'php_version' => '8.3',
        ]);

        expect($content)
            ->toContain('FROM dunglas/frankenphp:1-php8.3')
            ->toContain('frankenphp", "php-server"')
            ->not->toContain('Caddyfile');
    });

    test('renders frankenphp worker dockerfile', function (): void {
        $content = $this->renderer->render('docker/Dockerfile.twig', [
            'server' => 'frankenphp-worker',
            'php_version' => '8.3',
        ]);

        expect($content)
            ->toContain('FROM dunglas/frankenphp:1-php8.3')
            ->toContain('COPY .seaman/Caddyfile')
            ->toContain('frankenphp", "run", "--config"');
    });

    test('uses correct php version in image tag', function (): void {
        $content = $this->renderer->render('docker/Dockerfile.twig', [
            'server' => 'frankenphp',
            'php_version' => '8.5',
        ]);

        expect($content)->toContain('dunglas/frankenphp:1-php8.5');
    });

    test('frankenphp includes maxminddb extension', function (): void {
        $content = $this->renderer->render('docker/Dockerfile.twig', [
            'server' => 'frankenphp',
            'php_version' => '8.3',
        ]);

        expect($content)
            ->toContain('libmaxminddb-dev')
            ->toContain('maxminddb');
    });
});

describe('Caddyfile.twig', function (): void {
    test('contains worker configuration', function (): void {
        $content = $this->renderer->render('docker/Caddyfile.twig', []);

        expect($content)
            ->toContain('frankenphp')
            ->toContain('worker {')
            ->toContain('file ./index.php')
            ->toContain('num {$PHP_WORKERS:2}');
    });

    test('serves on port 80', function (): void {
        $content = $this->renderer->render('docker/Caddyfile.twig', []);

        expect($content)->toContain(':80 {');
    });
});
