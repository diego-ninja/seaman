<?php

// ABOUTME: Enumeration of lifecycle events that plugins can hook into.
// ABOUTME: Used with OnLifecycle attribute to specify hook timing.

declare(strict_types=1);

namespace Seaman\Plugin;

enum LifecycleEvent: string
{
    case BeforeInit = 'before:init';
    case AfterInit = 'after:init';
    case BeforeStart = 'before:start';
    case AfterStart = 'after:start';
    case BeforeStop = 'before:stop';
    case AfterStop = 'after:stop';
    case BeforeRebuild = 'before:rebuild';
    case AfterRebuild = 'after:rebuild';
    case BeforeDestroy = 'before:destroy';
    case AfterDestroy = 'after:destroy';
}
