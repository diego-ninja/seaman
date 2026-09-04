<?php

// ABOUTME: Tests for Dockerfile.twig template rendering.
// ABOUTME: Validates correct output for each server type.

declare(strict_types=1);

use Seaman\Service\TemplateRenderer;
use Seaman\ValueObject\VolumeConfig;
use Symfony\Component\Yaml\Yaml;

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

/**
 * @return array{rendered: string, service: array<string, mixed>}
 */
function renderElasticsearchTemplateForTest(string $variant): array
{
    if ($variant === 'core') {
        $renderer = new TemplateRenderer(__DIR__ . '/../../../src/Template');
        $rendered = $renderer->render('docker/services/elasticsearch.twig', [
            'name' => 'elasticsearch',
            'service' => new class {
                public string $version = '8.17.0';
            },
            'project_name' => 'test-project',
            'proxy_enabled' => false,
            'labels' => [],
            'volumes' => new VolumeConfig([]),
        ]);
    } else {
        $renderer = new TemplateRenderer(__DIR__ . '/../../../plugins/elasticsearch/templates');
        $rendered = $renderer->render('elasticsearch.yaml.twig', [
            'config' => [
                'version' => '8.17.0',
                'port' => 9200,
                'security_enabled' => true,
                'password' => 'pa ss$word #tag',
            ],
            'project' => ['name' => 'test-project'],
        ]);
    }

    /** @var array{services: array{elasticsearch: array<string, mixed>}} $compose */
    $compose = Yaml::parse("services:\n" . $rendered);

    return [
        'rendered' => $rendered,
        'service' => $compose['services']['elasticsearch'],
    ];
}

test('core Elasticsearch template configures its bootstrap password via Compose interpolation', function (): void {
    $template = renderElasticsearchTemplateForTest('core');

    expect($template['service']['environment'])->toContain(
        'ELASTIC_PASSWORD=${ELASTICSEARCH_PASSWORD:-seaman}',
    );
});

test('plugin Elasticsearch template preserves special characters in its bootstrap password', function (): void {
    $template = renderElasticsearchTemplateForTest('plugin');

    expect($template['service']['environment'])->toContain('ELASTIC_PASSWORD=pa ss$$word #tag')
        ->and($template['rendered'])->toContain("- 'ELASTIC_PASSWORD=pa ss\$\$word #tag'");
});

test('Elasticsearch templates authenticate their healthcheck', function (string $variant): void {
    $template = renderElasticsearchTemplateForTest($variant);

    /** @var array{test: list<string>} $healthcheck */
    $healthcheck = $template['service']['healthcheck'];

    expect($healthcheck['test'][1])
        ->toContain('-u "elastic:$${ELASTIC_PASSWORD:-seaman}"')
        ->toContain('/_cluster/health');
})->with([
    'core template' => ['core'],
    'plugin template' => ['plugin'],
]);
