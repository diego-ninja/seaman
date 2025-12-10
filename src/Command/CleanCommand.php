<?php

declare(strict_types=1);

// ABOUTME: Removes all Seaman-generated files from the project.
// ABOUTME: Restores backed-up docker-compose.yml and cleans .env if applicable.

namespace Seaman\Command;

use Seaman\Contract\Decorable;
use Seaman\Enum\DnsProvider;
use Seaman\Enum\OperatingMode;
use Seaman\Service\ConfigManager;
use Seaman\Service\DnsManager;
use Seaman\Service\DockerManager;
use Seaman\UI\Prompts;
use Seaman\UI\Terminal;
use Seaman\UI\Widget\Box\Box;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'seaman:clean',
    description: 'Remove all seaman-generated files from the project',
    aliases: ['clean'],
)]
class CleanCommand extends ModeAwareCommand implements Decorable
{
    /** @var list<string> */
    private const array FILES_TO_REMOVE = [
        'docker-compose.yml',
        'seaman.yaml',
    ];

    /** @var list<string> */
    private const array DIRECTORIES_TO_REMOVE = [
        '.seaman',
        '.devcontainer',
    ];

    public function __construct(
        private readonly DockerManager $dockerManager,
        private readonly ConfigManager $configManager,
        private readonly DnsManager $dnsHelper,
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
        $backupFile = $this->findDockerComposeBackup($projectRoot);
        $hasSeamanEnvSection = $this->hasSeamanEnvSection($projectRoot);
        $dnsInfo = $this->getDnsCleanupInfo($projectRoot);

        if (empty($filesToRemove) && empty($directoriesToRemove)) {
            Terminal::success('No Seaman files found to clean.');
            return Command::SUCCESS;
        }

        $this->displayFilesToRemove($filesToRemove, $directoriesToRemove, $backupFile, $hasSeamanEnvSection, $dnsInfo);

        if (!Prompts::confirm('This will remove all Seaman files. Are you sure?')) {
            Terminal::success('Operation cancelled');
            return Command::SUCCESS;
        }

        // Stop containers first if docker-compose.yml exists
        if (in_array($projectRoot . '/docker-compose.yml', $filesToRemove, true)) {
            $this->stopContainers();
        }

        // Clean DNS configuration before removing config files
        if ($dnsInfo !== null) {
            $this->cleanDnsConfiguration($dnsInfo['projectName'], $dnsInfo['provider']);
        }

        // Remove files and directories
        $this->removeFiles($filesToRemove);
        $this->removeDirectories($directoriesToRemove);

        // Restore docker-compose.yml backup if it exists
        if ($backupFile !== null) {
            $this->restoreDockerComposeBackup($projectRoot, $backupFile);
        }

        // Clean Seaman variables from .env
        if ($hasSeamanEnvSection) {
            $this->cleanEnvFile($projectRoot);
        }

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
     * @param array{projectName: string, provider: DnsProvider}|null $dnsInfo
     */
    private function displayFilesToRemove(
        array $files,
        array $directories,
        ?string $backupFile,
        bool $hasSeamanEnvSection,
        ?array $dnsInfo = null,
    ): void {
        $removeLines = [];

        foreach ($files as $file) {
            $removeLines[] = sprintf(' 🔹 Remove %s file', basename($file));
        }

        foreach ($directories as $dir) {
            $removeLines[] = sprintf(' 🔹 Remove %s directory', basename($dir));
        }

        if ($dnsInfo !== null) {
            $removeLines[] = sprintf(' 🔹 Disable DNS entries from %s', $dnsInfo['provider']->getDisplayName());
        }

        if ($hasSeamanEnvSection) {
            $removeLines[] = ' 🔹 Clean environment variables from .env';
        }

        if ($backupFile !== null) {
            $removeLines[] = '';
            $removeLines[] = sprintf(' 🔹 Restore docker-compose.yml from <fg=gray>%s</>', basename($backupFile));
        }

        $message = Terminal::render(implode("\n", $removeLines)) ?? implode("\n", $removeLines);

        new Box(
            title: 'Cleanup Summary',
            message: "\nThe following actions will be performed: \n\n" . $message . "\n",
            color: 'cyan',
        )->display();
    }

    private function stopContainers(): void
    {
        try {
            $result = $this->dockerManager->destroy();
            if ($result->isSuccessful()) {
                Terminal::success('Containers stopped and removed');
            }
        } catch (\RuntimeException) {
            // Docker compose file might be invalid or containers not running
            Terminal::info('No containers to stop');
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
            \RecursiveIteratorIterator::CHILD_FIRST,
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

    /**
     * Find the most recent docker-compose.yml backup file.
     */
    private function findDockerComposeBackup(string $projectRoot): ?string
    {
        $pattern = $projectRoot . '/docker-compose.yml.backup-*';
        $backups = glob($pattern);

        if (empty($backups)) {
            return null;
        }

        // Sort by modification time, newest first
        usort($backups, fn(string $a, string $b): int => filemtime($b) <=> filemtime($a));

        return $backups[0];
    }

    /**
     * Check if .env file contains Seaman managed section.
     */
    private function hasSeamanEnvSection(string $projectRoot): bool
    {
        $envPath = $projectRoot . '/.env';

        if (!file_exists($envPath)) {
            return false;
        }

        $content = file_get_contents($envPath);
        if ($content === false) {
            return false;
        }

        return str_contains($content, '---- SEAMAN MANAGED ----');
    }

    /**
     * Restore docker-compose.yml from backup and remove all backup files.
     */
    private function restoreDockerComposeBackup(string $projectRoot, string $backupFile): void
    {
        $targetPath = $projectRoot . '/docker-compose.yml';

        // Restore from backup
        if (copy($backupFile, $targetPath)) {
            Terminal::success('Restored docker-compose.yml from backup');
        } else {
            Terminal::error('Failed to restore docker-compose.yml from backup');
            return;
        }

        // Remove all backup files
        $pattern = $projectRoot . '/docker-compose.yml.backup-*';
        $backups = glob($pattern);

        if ($backups !== false) {
            foreach ($backups as $backup) {
                unlink($backup);
            }
            Terminal::success('Removed backup files');
        }
    }

    /**
     * Remove Seaman managed section from .env file.
     */
    private function cleanEnvFile(string $projectRoot): void
    {
        $envPath = $projectRoot . '/.env';

        if (!file_exists($envPath)) {
            return;
        }

        $content = file_get_contents($envPath);
        if ($content === false) {
            return;
        }

        $lines = explode("\n", $content);
        $cleanedLines = [];
        $inSeamanSection = false;

        foreach ($lines as $line) {
            // Detect Seaman managed section markers
            if (str_contains($line, '---- SEAMAN MANAGED ----')) {
                $inSeamanSection = true;
                continue;
            }
            if (str_contains($line, '---- END SEAMAN MANAGED ----')) {
                $inSeamanSection = false;
                continue;
            }

            // Keep lines outside Seaman section
            if (!$inSeamanSection) {
                $cleanedLines[] = $line;
            }
        }

        // Remove trailing empty lines
        while (!empty($cleanedLines) && trim(end($cleanedLines)) === '') {
            array_pop($cleanedLines);
        }

        $cleanedContent = implode("\n", $cleanedLines);

        // If file is empty after cleaning, remove it entirely
        if (trim($cleanedContent) === '') {
            unlink($envPath);
            Terminal::success('Removed empty .env file');
            return;
        }

        // Add final newline
        $cleanedContent .= "\n";

        if (file_put_contents($envPath, $cleanedContent) !== false) {
            Terminal::success('Cleaned Seaman variables from .env');
        } else {
            Terminal::error('Failed to clean .env file');
        }
    }

    /**
     * Get DNS cleanup information from seaman config.
     *
     * @return array{projectName: string, provider: DnsProvider}|null
     */
    private function getDnsCleanupInfo(string $projectRoot): ?array
    {
        try {
            $config = $this->configManager->load();
            $proxy = $config->proxy();

            if (!$proxy->enabled || $proxy->dnsProvider === null) {
                return null;
            }

            return [
                'projectName' => $config->projectName,
                'provider' => $proxy->dnsProvider,
            ];
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * Clean DNS configuration based on the provider used.
     */
    private function cleanDnsConfiguration(string $projectName, DnsProvider $provider): void
    {
        $result = $this->dnsHelper->executeDnsCleanup($projectName, $provider);

        foreach ($result['messages'] as $message) {
            if ($result['success']) {
                Terminal::success($message);
            } else {
                Terminal::error($message);
            }
        }
    }
}
