<?php

declare(strict_types=1);

// ABOUTME: Tests for TemplateRenderer service.
// ABOUTME: Validates Twig template rendering.

namespace Seaman\Tests\Unit\Service;

use Seaman\Service\TemplateRenderer;

/**
 * @property TemplateRenderer $renderer
 */
beforeEach(function () {
    $templateDir = __DIR__ . '/../../../src/Template';
    $this->renderer = new TemplateRenderer($templateDir);
});

test('renders simple template', function () {
    // Create a temporary template for testing
    $templateDir = __DIR__ . '/../../../src/Template';
    $testTemplate = $templateDir . '/test.twig';
    file_put_contents($testTemplate, 'Hello {{ name }}!');

    /** @var TemplateRenderer $renderer */
    $renderer = $this->renderer;
    $result = $renderer->render('test.twig', ['name' => 'World']);

    expect($result)->toBe('Hello World!');

    // Cleanup
    unlink($testTemplate);
});

test('renders template with arrays', function () {
    $templateDir = __DIR__ . '/../../../src/Template';
    $testTemplate = $templateDir . '/list.twig';
    file_put_contents($testTemplate, '{% for item in items %}{{ item }}{% if not loop.last %}, {% endif %}{% endfor %}');

    /** @var TemplateRenderer $renderer */
    $renderer = $this->renderer;
    $result = $renderer->render('list.twig', ['items' => ['a', 'b', 'c']]);

    expect($result)->toBe('a, b, c');

    unlink($testTemplate);
});

test('throws when template not found', function () {
    /** @var TemplateRenderer $renderer */
    $renderer = $this->renderer;
    $renderer->render('nonexistent.twig', []);
})->throws(\RuntimeException::class);
