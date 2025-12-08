<?php

declare(strict_types=1);

// ABOUTME: Removes all Seaman-generated files from the project.
// ABOUTME: Stops containers first, then deletes .seaman/, .devcontainer/, docker-compose.yml, and seaman.yaml.

namespace Seaman\Command;

use Seaman\Contract\Decorable;
use Seaman\Enum\OperatingMode;
use Seaman\Service\DockerManager;
use Seaman\UI\Prompts;
use Seaman\UI\Terminal;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'seaman:clean',
    description: 'Remove all Seaman-generated files from the project',
    aliases: ['clean'],
)]
class CleanCommand extends ModeAwareCommand implements Decorable
{
    /** @var list<string> */
    private const FILES_TO_REMOVE = [
        'docker-compose.yml',
        'seaman.yaml',
    ];

    /** @var list<string> */
    private const DIRECTORIES_TO_REMOVE = [
        '.seaman',
        '.devcontainer',
    ];

    public function __construct(
        private readonly DockerManager $dockerManager,
    ) {
        parent::__construct();
    }

    public function supportsMode(OperatingMode $mode): bool
    {
        return true; // Works in all modes
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $projectRoot = (string) getcwd();

        $filesToRemove = $this->findFilesToRemove($projectRoot);
        $directoriesToRemove = $this->findDirectoriesToRemove($projectRoot);

        if (empty($filesToRemove) && empty($directoriesToRemove)) {
            Terminal::success('No Seaman files found to clean.');
            return Command::SUCCESS;
        }

        $this->displayFilesToRemove($filesToRemove, $directoriesToRemove);

        if (!Prompts::confirm('This will remove all Seaman files. Are you sure?')) {
            Terminal::success('Cancelled');
            return Command::SUCCESS;
        }

        // Stop containers first if docker-compose.yml exists
        if (in_array($projectRoot . '/docker-compose.yml', $filesToRemove, true)) {
            $this->stopContainers();
        }

        // Remove files and directories
        $this->removeFiles($filesToRemove);
        $this->removeDirectories($directoriesToRemove);

        Terminal::success('Seaman files cleaned successfully!');
        return Command::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function findFilesToRemove(string $projectRoot): array
    {
        $files = [];
        foreach (self::FILES_TO_REMOVE as $file) {
            $path = $projectRoot . '/' . $file;
            if (file_exists($path)) {
                $files[] = $path;
            }
        }
        return $files;
    }

    /**
     * @return list<string>
     */
    private function findDirectoriesToRemove(string $projectRoot): array
    {
        $directories = [];
        foreach (self::DIRECTORIES_TO_REMOVE as $dir) {
            $path = $projectRoot . '/' . $dir;
            if (is_dir($path)) {
                $directories[] = $path;
            }
        }
        return $directories;
    }

    /**
     * @param list<string> $files
     * @param list<string> $directories
     */
    private function displayFilesToRemove(array $files, array $directories): void
    {
        Terminal::output()->writeln('');
        Terminal::output()->writeln('  <fg=yellow>The following will be removed:</>');
        Terminal::output()->writeln('');

        foreach ($files as $file) {
            Terminal::output()->writeln('    • ' . basename($file));
        }

        foreach ($directories as $dir) {
            Terminal::output()->writeln('    • ' . basename($dir) . '/');
        }

        Terminal::output()->writeln('');
    }

    private function stopContainers(): void
    {
        Terminal::output()->writeln('  Stopping containers...');

        try {
            $result = $this->dockerManager->destroy();
            if ($result->isSuccessful()) {
                Terminal::output()->writeln('  <fg=green>✓</> Containers stopped and removed');
            }
        } catch (\RuntimeException) {
            // Docker compose file might be invalid or containers not running
            Terminal::output()->writeln('  <fg=gray>No containers to stop</>');
        }
    }

    /**
     * @param list<string> $files
     */
    private function removeFiles(array $files): void
    {
        foreach ($files as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
    }

    /**
     * @param list<string> $directories
     */
    private function removeDirectories(array $directories): void
    {
        foreach ($directories as $dir) {
            if (is_dir($dir)) {
                $this->removeDirectoryRecursively($dir);
            }
        }
    }

    private function removeDirectoryRecursively(string $dir): void
    {
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $file) {
            /** @var \SplFileInfo $file */
            if ($file->isDir()) {
                rmdir($file->getRealPath());
            } else {
                unlink($file->getRealPath());
            }
        }

        rmdir($dir);
    }
}
