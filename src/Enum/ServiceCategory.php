<?php

// ABOUTME: Categories for grouping Docker services.
// ABOUTME: Used for organization in service listings.

declare(strict_types=1);

namespace Seaman\Enum;

enum ServiceCategory: string
{
    case Database = 'database';
    case Cache = 'cache';
    case Queue = 'queue';
    case Search = 'search';
    case DevTools = 'dev-tools';
    case Proxy = 'proxy';
    case Misc = 'misc';
}
