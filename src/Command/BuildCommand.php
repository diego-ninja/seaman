<?php

declare(strict_types=1);

// ABOUTME: Builds PHAR executable using Box.
// ABOUTME: Creates distributable seaman.phar in build directory.

namespace Seaman\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;

#[AsCommand(
    name: 'seaman:build',
    description: 'Build PHAR executable using Box',
    aliases: ['build'],
)]
class BuildCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $projectRoot = (string) getcwd();

        // Ensure build directory exists
        $buildDir = $projectRoot . '/build';
        if (!is_dir($buildDir)) {
            if (!mkdir($buildDir, 0755, true)) {
                $io->error('Failed to create build directory');
                return Command::FAILURE;
            }
        }

        // Check if box is available
        $boxPath = $projectRoot . '/vendor/bin/box';
        if (!file_exists($boxPath)) {
            $io->error('Box not found. Please run: composer install --dev');
            return Command::FAILURE;
        }

        $io->section('Building PHAR');
        $io->text('Using Box to compile seaman.phar...');

        // Run box compile
        $process = new Process(
            [$boxPath, 'compile', '--working-dir=' . $projectRoot],
            $projectRoot,
            null,
            null,
            300, // 5 minutes timeout
        );

        $process->run(function (string $type, string $buffer) use ($output): void {
            $output->write($buffer);
        });

        if (!$process->isSuccessful()) {
            $io->error('Failed to build PHAR');
            $io->text($process->getErrorOutput());
            return Command::FAILURE;
        }

        $pharPath = $buildDir . '/seaman.phar';
        if (!file_exists($pharPath)) {
            $io->error('PHAR file was not created at expected location: ' . $pharPath);
            return Command::FAILURE;
        }

        $io->success('PHAR built successfully: ' . $pharPath);
        $io->text('You can now distribute build/seaman.phar');

        return Command::SUCCESS;
    }
}
