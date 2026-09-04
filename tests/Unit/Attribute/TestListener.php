<?php

declare(strict_types=1);

// ABOUTME: Fixture listener used to verify event-listener attribute metadata.
// ABOUTME: Keeps the fixture PSR-4 compliant and separate from the Pest test file.

namespace Seaman\Tests\Unit\Attribute;

use Seaman\Attribute\AsEventListener;
use Symfony\Component\Console\ConsoleEvents;

#[AsEventListener(event: ConsoleEvents::TERMINATE, priority: 50)]
final class TestListener
{
    public function __invoke(object $event): void {}
}
