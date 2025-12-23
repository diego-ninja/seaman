<?php

// ABOUTME: Extracts console commands from plugin methods.
// ABOUTME: Scans for methods with ProvidesCommand attribute.

declare(strict_types=1);

namespace Seaman\Plugin\Extractor;

use ReflectionClass;
use ReflectionMethod;
use Seaman\Plugin\Attribute\ProvidesCommand;
use Seaman\Plugin\PluginInterface;
use Symfony\Component\Console\Command\Command;

final readonly class CommandExtractor
{
    /**
     * @return list<Command>
     */
    public function extract(PluginInterface $plugin): array
    {
        $commands = [];
        $reflection = new ReflectionClass($plugin);

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $attributes = $method->getAttributes(ProvidesCommand::class);

            if (empty($attributes)) {
                continue;
            }

            /** @var Command $command */
            $command = $method->invoke($plugin);
            $commands[] = $command;
        }

        return $commands;
    }
}
