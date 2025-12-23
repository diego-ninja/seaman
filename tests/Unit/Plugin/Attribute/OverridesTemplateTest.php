<?php

declare(strict_types=1);

// ABOUTME: Tests for OverridesTemplate attribute.
// ABOUTME: Validates template path storage and targeting.

namespace Seaman\Tests\Unit\Plugin\Attribute;

use Seaman\Plugin\Attribute\OverridesTemplate;

test('OverridesTemplate stores template path', function (): void {
    $attribute = new OverridesTemplate(
        template: 'docker/app.dockerfile.twig',
    );

    expect($attribute->template)->toBe('docker/app.dockerfile.twig');
});

test('OverridesTemplate targets methods only', function (): void {
    $reflection = new \ReflectionClass(OverridesTemplate::class);
    $attributes = $reflection->getAttributes(\Attribute::class);

    $attrInstance = $attributes[0]->newInstance();
    expect($attrInstance->flags)->toBe(\Attribute::TARGET_METHOD);
});
