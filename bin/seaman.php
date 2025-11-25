#!/usr/bin/env php
<?php

// ABOUTME: CLI entry point for Seaman application.
// ABOUTME: Bootstraps Symfony Console Application.

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$application = new Seaman\Application();
exit($application->run());
