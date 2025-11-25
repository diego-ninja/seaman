<?php

// ABOUTME: Main Symfony Console Application for Seaman.
// ABOUTME: Registers and manages all CLI commands.

declare(strict_types=1);

namespace Seaman;

use Symfony\Component\Console\Application as BaseApplication;

class Application extends BaseApplication
{
    public function __construct()
    {
        parent::__construct('Seaman', '1.0.0');
    }
}
