<?php

declare(strict_types=1);

// ABOUTME: Configuration for PHP CS Fixer.
// ABOUTME: Enforces PER coding standard across the codebase.

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__ . '/src')
    ->in(__DIR__ . '/tests')
    ->exclude('Fixtures')
    ->name('*.php')
    ->ignoreDotFiles(true)
    ->ignoreVCS(true);

return new PhpCsFixer\Config()
    ->setRules([
        '@PER-CS' => true,
        '@PER-CS:risky' => true,
        'declare_strict_types' => true,
        'strict_param' => true,
        'array_syntax' => ['syntax' => 'short'],
    ])
    ->setFinder($finder)
    ->setRiskyAllowed(true);