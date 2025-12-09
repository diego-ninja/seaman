<?php

declare(strict_types=1);

// ABOUTME: Removes all Seaman-generated files from the project.
// ABOUTME: Restores backed-up docker-compose.yml and cleans .env if applicable.

namespace Seaman\Command;

use Seaman\Contract\Decorable;
use Seaman\Enum\DnsProvider;
use Seaman\Enum\OperatingMode;
use Seaman\Service\ConfigManager;
use Seaman\Service\DnsConfigurationHelper;
use Seaman\Service\DockerManager;
use Seaman\Service\Process\RealCommandExecutor;
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
        private readonly ConfigManager $configManager,
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
            Terminal::success('Cancelled');
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
        Terminal::output()->writeln('');
        Terminal::output()->writeln('  <fg=yellow>The following will be removed:</>');
        Terminal::output()->writeln('');

        foreach ($files as $file) {
            Terminal::output()->writeln('    • ' . basename($file));
        }

        foreach ($directories as $dir) {
            Terminal::output()->writeln('    • ' . basename($dir) . '/');
        }

        if ($dnsInfo !== null) {
            Terminal::output()->writeln('    • DNS entries (' . $dnsInfo['provider']->getDisplayName() . ')');
        }

        Terminal::output()->writeln('');

        if ($backupFile !== null) {
            Terminal::output()->writeln('  <fg=cyan>The following will be restored:</>');
            Terminal::output()->writeln('');
            Terminal::output()->writeln('    • docker-compose.yml (from ' . basename($backupFile) . ')');
            Terminal::output()->writeln('');
        }

        if ($hasSeamanEnvSection) {
            Terminal::output()->writeln('  <fg=cyan>Seaman variables will be removed from .env</>');
            Terminal::output()->writeln('');
        }
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

        if ($backups === false || empty($backups)) {
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
            Terminal::output()->writeln('  <fg=green>✓</> Restored docker-compose.yml from backup');
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
            Terminal::output()->writeln('  <fg=green>✓</> Removed backup files');
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
            Terminal::output()->writeln('  <fg=green>✓</> Removed empty .env file');
            return;
        }

        // Add final newline
        $cleanedContent .= "\n";

        if (file_put_contents($envPath, $cleanedContent) !== false) {
            Terminal::output()->writeln('  <fg=green>✓</> Cleaned Seaman variables from .env');
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
        Terminal::output()->writeln('  Cleaning DNS configuration...');

        $executor = new RealCommandExecutor();
        $dnsHelper = new DnsConfigurationHelper($executor);
        $result = $dnsHelper->cleanupDns($projectName, $provider);

        if (!$result->automatic) {
            foreach ($result->instructions as $instruction) {
                Terminal::output()->writeln("    {$instruction}");
            }
            return;
        }

        // Handle hosts file cleanup (requires writing new content)
        if ($result->type === 'hosts-file-cleanup' && $result->configContent !== null) {
            $tempFile = tempnam(sys_get_temp_dir(), 'hosts_');
            if ($tempFile === false) {
                Terminal::error('Failed to create temp file for hosts cleanup');
                return;
            }

            file_put_contents($tempFile, $result->configContent);

            $copyResult = $executor->execute(['sudo', 'cp', $tempFile, '/etc/hosts']);
            unlink($tempFile);

            if ($copyResult->isSuccessful()) {
                Terminal::output()->writeln('  <fg=green>✓</> Removed DNS entries from /etc/hosts');
            } else {
                Terminal::error('Failed to update /etc/hosts (requires sudo)');
            }
            return;
        }

        // Handle file removal for other providers
        if ($result->configPath !== null && file_exists($result->configPath)) {
            $removeResult = $executor->execute(['sudo', 'rm', '-f', $result->configPath]);

            if ($removeResult->isSuccessful()) {
                Terminal::output()->writeln("  <fg=green>✓</> Removed {$result->configPath}");

                // Restart service if needed
                if ($result->restartCommand !== null) {
                    $parts = explode(' ', $result->restartCommand);
                    $restartResult = $executor->execute($parts);
                    if ($restartResult->isSuccessful()) {
                        Terminal::output()->writeln('  <fg=green>✓</> Restarted DNS service');
                    }
                }
            } else {
                Terminal::error("Failed to remove {$result->configPath} (requires sudo)");
            }
        }
    }
}
