<?php

declare(strict_types=1);

// ABOUTME: Removes all Seaman-generated files from the project.
// ABOUTME: Restores backed-up Compose files and cleans .env if applicable.

namespace Seaman\Command;

use Seaman\Contract\Decorable;
use Seaman\Enum\DnsProvider;
use Seaman\Enum\OperatingMode;
use Seaman\Service\ComposeFileLocator;
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

        $composeFile = $this->findManagedComposeFile($projectRoot);
        $filesToRemove = $this->findFilesToRemove($projectRoot, $composeFile);
        $directoriesToRemove = $this->findDirectoriesToRemove($projectRoot);
        $backupFile = $this->findDockerComposeBackup($projectRoot);
        $hasSeamanEnvSection = $this->hasSeamanEnvSection($projectRoot);
        $dnsInfo = $this->getDnsCleanupInfo($projectRoot);

        if ($composeFile !== null && is_link($composeFile)) {
            Terminal::error('Cannot clean a project whose Docker Compose file is a symbolic link.');

            return Command::FAILURE;
        }

        if ($backupFile !== null && $this->backupWouldOverwriteComposeFile($backupFile, $composeFile)) {
            $target = $this->getBackupTarget($backupFile);
            Terminal::error(sprintf(
                'Cannot restore %s because it would overwrite an existing Compose file.',
                $target === null ? 'the backup' : basename($target),
            ));

            return Command::FAILURE;
        }

        if (
            empty($filesToRemove)
            && empty($directoriesToRemove)
            && $backupFile === null
            && !$hasSeamanEnvSection
            && $dnsInfo === null
        ) {
            Terminal::success('No Seaman files found to clean.');
            return Command::SUCCESS;
        }

        $this->displayFilesToRemove($filesToRemove, $directoriesToRemove, $backupFile, $hasSeamanEnvSection, $dnsInfo);

        if (!Prompts::confirm('This will remove all Seaman files. Are you sure?')) {
            Terminal::success('Operation cancelled');
            return Command::SUCCESS;
        }

        // Stop containers before removing the exact Compose file shown in the preview
        if ($composeFile !== null) {
            if (!$this->stopContainers()) {
                return Command::FAILURE;
            }
        }

        // Clean DNS configuration before removing config files
        if ($dnsInfo !== null) {
            if (!$this->cleanDnsConfiguration($dnsInfo['projectName'], $dnsInfo['provider'])) {
                return Command::FAILURE;
            }
        }

        // Remove files and directories
        $this->removeFiles($filesToRemove);
        $this->removeDirectories($directoriesToRemove);

        // Restore the original Compose file backup if it exists
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
    private function findFilesToRemove(string $projectRoot, ?string $composeFile): array
    {
        $files = [];
        foreach (self::FILES_TO_REMOVE as $file) {
            $path = $projectRoot . '/' . $file;
            if (file_exists($path) || is_link($path)) {
                $files[] = $path;
            }
        }

        if ($composeFile !== null) {
            $files[] = $composeFile;
        }

        return $files;
    }

    private function findManagedComposeFile(string $projectRoot): ?string
    {
        $hasManagedArtifacts = is_dir($projectRoot . '/.seaman')
            || is_link($projectRoot . '/.seaman')
            || file_exists($projectRoot . '/seaman.yaml')
            || $this->findDockerComposeBackup($projectRoot) !== null
            || $this->hasSeamanEnvSection($projectRoot);

        if (!$hasManagedArtifacts) {
            return null;
        }

        return (new ComposeFileLocator($projectRoot))->find();
    }

    /**
     * @return list<string>
     */
    private function findDirectoriesToRemove(string $projectRoot): array
    {
        $directories = [];
        foreach (self::DIRECTORIES_TO_REMOVE as $dir) {
            $path = $projectRoot . '/' . $dir;
            if (is_dir($path) || is_link($path)) {
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
            $target = $this->getBackupTarget($backupFile);
            $removeLines[] = sprintf(
                ' 🔹 Restore %s from <fg=gray>%s</>',
                $target === null ? 'Docker Compose file' : basename($target),
                basename($backupFile),
            );
        }

        $message = Terminal::render(implode("\n", $removeLines)) ?? implode("\n", $removeLines);

        new Box(
            title: 'Cleanup Summary',
            message: "\nThe following actions will be performed: \n\n" . $message . "\n",
            color: 'cyan',
        )->display();
    }

    private function stopContainers(): bool
    {
        try {
            $result = $this->dockerManager->destroy();
            if ($result->isSuccessful()) {
                Terminal::success('Containers stopped and removed');
                return true;
            }

            Terminal::error('Failed to stop and remove containers');
            Terminal::output()->writeln($result->errorOutput);
        } catch (\RuntimeException $exception) {
            Terminal::error($exception->getMessage());
        }

        return false;
    }

    /**
     * @param list<string> $files
     */
    private function removeFiles(array $files): void
    {
        foreach ($files as $file) {
            if (file_exists($file) || is_link($file)) {
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
            if (is_link($dir)) {
                unlink($dir);
            } elseif (is_dir($dir)) {
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
            $path = $file->getPathname();
            if ($file->isLink()) {
                unlink($path);
            } elseif ($file->isDir()) {
                rmdir($path);
            } else {
                unlink($path);
            }
        }

        rmdir($dir);
    }

    /**
     * Find the most recent supported Docker Compose backup file.
     */
    private function findDockerComposeBackup(string $projectRoot): ?string
    {
        $backups = [];
        foreach (ComposeFileLocator::supportedFilenames() as $filename) {
            $matches = glob($projectRoot . '/' . $filename . '.backup-*');
            if ($matches !== false) {
                $backups = array_merge($backups, $matches);
            }
        }

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
     * Restore the original Compose filename and remove all Compose backups.
     */
    private function restoreDockerComposeBackup(string $projectRoot, string $backupFile): void
    {
        $targetPath = $this->getBackupTarget($backupFile);
        if ($targetPath === null) {
            Terminal::error('Could not determine original Docker Compose filename');
            return;
        }

        // Restore from backup
        if (copy($backupFile, $targetPath)) {
            Terminal::success(sprintf('Restored %s from backup', basename($targetPath)));
        } else {
            Terminal::error('Failed to restore docker-compose.yml from backup');
            return;
        }

        // Remove all backup files
        foreach (ComposeFileLocator::supportedFilenames() as $filename) {
            $backups = glob($projectRoot . '/' . $filename . '.backup-*');
            if ($backups !== false) {
                foreach ($backups as $backup) {
                    unlink($backup);
                }
            }
        }

        Terminal::success('Removed backup files');
    }

    private function getBackupTarget(string $backupFile): ?string
    {
        $marker = strrpos($backupFile, '.backup-');

        return $marker === false ? null : substr($backupFile, 0, $marker);
    }

    private function backupWouldOverwriteComposeFile(string $backupFile, ?string $composeFile): bool
    {
        $target = $this->getBackupTarget($backupFile);

        return $target !== null
            && $target !== $composeFile
            && (file_exists($target) || is_link($target));
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
    private function cleanDnsConfiguration(string $projectName, DnsProvider $provider): bool
    {
        $result = $this->dnsHelper->executeDnsCleanup($projectName, $provider);

        foreach ($result['messages'] as $message) {
            if ($result['success']) {
                Terminal::success($message);
            } else {
                Terminal::error($message);
            }
        }

        return $result['success'];
    }
}
