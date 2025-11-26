#!/usr/bin/env php
<?php

// ABOUTME: CLI entry point for Seaman application.
// ABOUTME: Bootstraps Symfony Console Application.

declare(strict_types=1);

use Innmind\Signals\Info;
use Innmind\Signals\Signal;
use Seaman\Signal\SignalHandler;
use Seaman\UI\Terminal;

if (false === in_array(PHP_SAPI, ['cli', 'phpdbg', 'embed'], true)) {
    echo PHP_EOL . 'This app may only be invoked from a command line, got "' . PHP_SAPI . '"' . PHP_EOL;
    exit(1);
}

(static function (): void {
    if (file_exists($autoload = __DIR__ . '/../vendor/autoload.php')) {
        include_once $autoload;
        return;
    }
    throw new RuntimeException('Unable to find the Composer autoloader.');
})();


SignalHandler::listen([Signal::interrupt, Signal::terminate], static function (Signal $signal, Info $info): void {
    Terminal::output()->writeln("\n\n 💔 Interrupted by user.");
    Terminal::restoreCursor();

    exit($signal->toInt());
});

$dotenv = Phar::running() !== ''
        ? Dotenv\Dotenv::createMutable(Phar::running(), '.env')
        : Dotenv\Dotenv::createMutable(getcwd(), '.env');

$dotenv->safeLoad();

$application = new Seaman\Application();
exit($application->run());
