<?php

// ABOUTME: Dispatches lifecycle events to registered plugin handlers.
// ABOUTME: Extracts and invokes handlers sorted by priority.

declare(strict_types=1);

namespace Seaman\Plugin;

use Seaman\Plugin\Extractor\LifecycleExtractor;

final readonly class PluginLifecycleDispatcher
{
    private LifecycleExtractor $extractor;

    public function __construct(
        private PluginRegistry $registry,
    ) {
        $this->extractor = new LifecycleExtractor();
    }

    public function dispatch(string $event, LifecycleEventData $data): void
    {
        $handlers = $this->collectHandlers($event);

        foreach ($handlers as $handler) {
            ($handler->handler)($data);
        }
    }

    /**
     * @return list<LifecycleHandler>
     */
    private function collectHandlers(string $event): array
    {
        $handlers = [];

        foreach ($this->registry->all() as $loadedPlugin) {
            $pluginHandlers = $this->extractor->extract($loadedPlugin->instance);

            foreach ($pluginHandlers as $handler) {
                if ($handler->event === $event) {
                    $handlers[] = $handler;
                }
            }
        }

        // Sort by priority (higher first)
        usort($handlers, fn(LifecycleHandler $a, LifecycleHandler $b): int => $b->priority <=> $a->priority);

        return $handlers;
    }
}
