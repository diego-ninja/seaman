<?php

declare(strict_types=1);

// ABOUTME: Helps configure DNS for local development domains.
// ABOUTME: Detects platform capabilities and provides setup instructions.

namespace Seaman\Service;

use Seaman\Contract\CommandExecutor;
use Seaman\Enum\DnsProvider;
use Seaman\Enum\Service;
use Seaman\Enum\ServiceExposureType;
use Seaman\ValueObject\DetectedDnsProvider;
use Seaman\ValueObject\DnsConfigurationResult;
use Seaman\ValueObject\ServiceCollection;

final readonly class DnsConfigurationHelper
{
    private const string HOSTS_MARKER_START = '# BEGIN Seaman -';
    private const string HOSTS_MARKER_END = '# END Seaman -';

    public function __construct(
        private CommandExecutor $executor,
        private ?PrivilegedExecutor $privilegedExecutor = null,
    ) {}

    /**
     * Get the privilege escalation command (pkexec or sudo).
     */
    private function getPrivilegeCommand(): string
    {
        return $this->privilegedExecutor?->getPrivilegeEscalationString() ?? 'sudo';
    }

    /**
     * Configure DNS for the project (legacy method for backward compatibility).
     */
    public function configure(string $projectName): DnsConfigurationResult
    {
        $recommended = $this->getRecommendedProvider($projectName);

        if ($recommended === null) {
            return $this->getManualInstructions($projectName);
        }

        return $this->configureProvider($projectName, $recommended->provider);
    }

    /**
     * Detect all available DNS providers on this system.
     *
     * @return list<DetectedDnsProvider>
     */
    public function detectAvailableProviders(string $projectName): array
    {
        /** @var list<DetectedDnsProvider> $providers */
        $providers = [];

        // macOS resolver (only on Darwin)
        if (PHP_OS_FAMILY === 'Darwin') {
            $providers[] = new DetectedDnsProvider(
                provider: DnsProvider::MacOSResolver,
                configPath: "/etc/resolver/{$projectName}.local",
                requiresSudo: true,
            );
        }

        // dnsmasq - only if it can actually run (port 53 available or already running)
        if ($this->hasDnsmasq() && $this->canUseDnsmasq()) {
            $providers[] = new DetectedDnsProvider(
                provider: DnsProvider::Dnsmasq,
                configPath: $this->getDnsmasqConfigPath($projectName),
                requiresSudo: true,
            );
        }

        // NetworkManager with dnsmasq plugin
        if ($this->hasNetworkManagerWithDnsmasq()) {
            $providers[] = new DetectedDnsProvider(
                provider: DnsProvider::NetworkManager,
                configPath: "/etc/NetworkManager/dnsmasq.d/seaman-{$projectName}.conf",
                requiresSudo: true,
            );
        }

        // systemd-resolved - but warn if stub listener is active
        if ($this->hasSystemdResolved() && !$this->hasSystemdResolvedStubListener()) {
            $providers[] = new DetectedDnsProvider(
                provider: DnsProvider::SystemdResolved,
                configPath: "/etc/systemd/resolved.conf.d/seaman-{$projectName}.conf",
                requiresSudo: true,
            );
        }

        // /etc/hosts is always available as fallback
        $providers[] = new DetectedDnsProvider(
            provider: DnsProvider::HostsFile,
            configPath: '/etc/hosts',
            requiresSudo: true,
        );

        // Sort by priority
        usort(
            $providers,
            fn(DetectedDnsProvider $a, DetectedDnsProvider $b): int
            => $a->provider->getPriority() <=> $b->provider->getPriority(),
        );

        return $providers;
    }

    /**
     * Get the recommended DNS provider for this system.
     */
    public function getRecommendedProvider(string $projectName): ?DetectedDnsProvider
    {
        $providers = $this->detectAvailableProviders($projectName);

        return $providers[0] ?? null;
    }

    /**
     * Configure DNS using a specific provider.
     */
    public function configureProvider(
        string $projectName,
        DnsProvider $provider,
        ?ServiceCollection $services = null,
    ): DnsConfigurationResult {
        return match ($provider) {
            DnsProvider::Dnsmasq => $this->configureDnsmasq($projectName),
            DnsProvider::SystemdResolved => $this->configureSystemdResolved($projectName),
            DnsProvider::NetworkManager => $this->configureNetworkManager($projectName),
            DnsProvider::MacOSResolver => $this->configureMacOSResolver($projectName),
            DnsProvider::HostsFile => $this->configureHostsFile($projectName, $services),
            DnsProvider::Manual => $this->getManualInstructions($projectName, $services),
        };
    }

    /**
     * Check if dnsmasq is available on the system.
     */
    public function hasDnsmasq(): bool
    {
        $result = $this->executor->execute(['which', 'dnsmasq']);
        return $result->isSuccessful();
    }

    /**
     * Check if systemd-resolved is active on the system.
     */
    public function hasSystemdResolved(): bool
    {
        $result = $this->executor->execute(['systemctl', 'is-active', 'systemd-resolved']);
        return $result->isSuccessful();
    }

    /**
     * Check if NetworkManager is active on the system.
     */
    public function hasNetworkManager(): bool
    {
        $result = $this->executor->execute(['systemctl', 'is-active', 'NetworkManager']);
        if (!$result->isSuccessful()) {
            return false;
        }

        return is_dir('/etc/NetworkManager');
    }

    /**
     * Check if NetworkManager is configured to use dnsmasq.
     */
    public function hasNetworkManagerWithDnsmasq(): bool
    {
        if (!$this->hasNetworkManager()) {
            return false;
        }

        // Check if dnsmasq plugin is enabled in NetworkManager
        $nmConfPath = '/etc/NetworkManager/NetworkManager.conf';
        if (!file_exists($nmConfPath)) {
            return false;
        }

        $content = file_get_contents($nmConfPath);
        if ($content === false) {
            return false;
        }

        return str_contains($content, 'dns=dnsmasq');
    }

    /**
     * Check if dnsmasq can actually be used (port 53 available or dnsmasq already running).
     */
    public function canUseDnsmasq(): bool
    {
        // Check if dnsmasq is already running
        $result = $this->executor->execute(['systemctl', 'is-active', 'dnsmasq']);
        if ($result->isSuccessful()) {
            return true;
        }

        // Check if port 53 is available (not blocked by systemd-resolved stub)
        return !$this->isPort53Occupied();
    }

    /**
     * Check if port 53 is occupied by something other than dnsmasq.
     */
    private function isPort53Occupied(): bool
    {
        // Check if systemd-resolved stub listener is active on 127.0.0.53:53
        $result = $this->executor->execute(['ss', '-tlnp']);
        if (!$result->isSuccessful()) {
            return false;
        }

        $output = $result->output;

        // If something is listening on 127.0.0.53:53 or 127.0.0.1:53, port is occupied
        return str_contains($output, '127.0.0.53:53') || str_contains($output, '127.0.0.1:53');
    }

    /**
     * Check if systemd-resolved stub listener is active (blocking port 53).
     */
    public function hasSystemdResolvedStubListener(): bool
    {
        if (!$this->hasSystemdResolved()) {
            return false;
        }

        return $this->isPort53Occupied();
    }

    /**
     * Configure DNS using dnsmasq.
     */
    private function configureDnsmasq(string $projectName): DnsConfigurationResult
    {
        $configPath = $this->getDnsmasqConfigPath($projectName);
        $configContent = "address=/.{$projectName}.local/127.0.0.1\n";
        $priv = $this->getPrivilegeCommand();

        $restartCommand = PHP_OS_FAMILY === 'Darwin'
            ? "{$priv} brew services restart dnsmasq"
            : "{$priv} systemctl restart dnsmasq";

        return new DnsConfigurationResult(
            type: 'dnsmasq',
            automatic: true,
            requiresSudo: true,
            configPath: $configPath,
            configContent: $configContent,
            instructions: [],
            restartCommand: $restartCommand,
        );
    }

    /**
     * Configure DNS using systemd-resolved.
     */
    private function configureSystemdResolved(string $projectName): DnsConfigurationResult
    {
        $configPath = "/etc/systemd/resolved.conf.d/seaman-{$projectName}.conf";
        $configContent = "[Resolve]\n";
        $configContent .= "DNS=127.0.0.1\n";
        $configContent .= "Domains=~{$projectName}.local\n";
        $priv = $this->getPrivilegeCommand();

        return new DnsConfigurationResult(
            type: 'systemd-resolved',
            automatic: true,
            requiresSudo: true,
            configPath: $configPath,
            configContent: $configContent,
            instructions: [],
            restartCommand: "{$priv} systemctl restart systemd-resolved",
        );
    }

    /**
     * Configure DNS using NetworkManager with dnsmasq.
     */
    private function configureNetworkManager(string $projectName): DnsConfigurationResult
    {
        $configPath = "/etc/NetworkManager/dnsmasq.d/seaman-{$projectName}.conf";
        $configContent = "address=/.{$projectName}.local/127.0.0.1\n";
        $priv = $this->getPrivilegeCommand();

        return new DnsConfigurationResult(
            type: 'networkmanager',
            automatic: true,
            requiresSudo: true,
            configPath: $configPath,
            configContent: $configContent,
            instructions: [],
            restartCommand: "{$priv} systemctl restart NetworkManager",
        );
    }

    /**
     * Configure DNS using macOS resolver.
     */
    private function configureMacOSResolver(string $projectName): DnsConfigurationResult
    {
        $configPath = "/etc/resolver/{$projectName}.local";
        $configContent = "nameserver 127.0.0.1\n";

        return new DnsConfigurationResult(
            type: 'macos-resolver',
            automatic: true,
            requiresSudo: true,
            configPath: $configPath,
            configContent: $configContent,
            instructions: [],
            restartCommand: null, // macOS resolver doesn't need restart
        );
    }

    /**
     * Configure DNS using /etc/hosts file.
     */
    private function configureHostsFile(string $projectName, ?ServiceCollection $services = null): DnsConfigurationResult
    {
        $hostsEntries = $this->getHostsEntries($projectName, $services);

        // Filter out entries that already exist
        $existingEntries = $this->getExistingHostsEntries();
        $newEntries = array_filter(
            $hostsEntries,
            fn(string $host): bool => !in_array($host, $existingEntries, true),
        );

        if ($newEntries === []) {
            return new DnsConfigurationResult(
                type: 'hosts-file',
                automatic: true,
                requiresSudo: false,
                configPath: '/etc/hosts',
                configContent: null,
                instructions: ['All DNS entries already exist in /etc/hosts'],
                restartCommand: null,
            );
        }

        // Use markers for easy cleanup
        $configContent = "\n" . self::HOSTS_MARKER_START . " {$projectName}\n";
        foreach ($newEntries as $host) {
            $configContent .= "127.0.0.1 {$host}\n";
        }
        $configContent .= self::HOSTS_MARKER_END . " {$projectName}\n";

        return new DnsConfigurationResult(
            type: 'hosts-file',
            automatic: true,
            requiresSudo: true,
            configPath: '/etc/hosts',
            configContent: $configContent,
            instructions: [],
            restartCommand: null,
        );
    }

    /**
     * Get all hosts entries needed for a project.
     *
     * @return list<string>
     */
    private function getHostsEntries(string $projectName, ?ServiceCollection $services = null): array
    {
        // Always include app and traefik
        $entries = [
            "app.{$projectName}.local",
            "traefik.{$projectName}.local",
        ];

        // Add entries for all ProxyOnly services
        if ($services !== null) {
            foreach ($services->enabled() as $name => $service) {
                if ($this->isProxyOnlyService($service->type)) {
                    $entries[] = "{$name}.{$projectName}.local";
                }
            }
        } else {
            // Fallback: include common ProxyOnly services
            $defaultServices = ['mailpit', 'dozzle', 'minio', 'rabbitmq', 'opensearch', 'elasticsearch'];
            foreach ($defaultServices as $name) {
                $entries[] = "{$name}.{$projectName}.local";
            }
        }

        return array_values(array_unique($entries));
    }

    /**
     * Determine if a service type should be exposed via proxy.
     */
    private function isProxyOnlyService(Service $service): bool
    {
        return match ($service) {
            Service::App,
            Service::Mailpit,
            Service::RabbitMq,
            Service::Dozzle,
            Service::MinIO,
            Service::Elasticsearch,
            Service::OpenSearch,
            Service::Traefik => true,
            default => false,
        };
    }

    /**
     * Get existing hosts entries from /etc/hosts.
     *
     * @return list<string>
     */
    private function getExistingHostsEntries(): array
    {
        if (!file_exists('/etc/hosts')) {
            return [];
        }

        $content = file_get_contents('/etc/hosts');
        if ($content === false) {
            return [];
        }

        $entries = [];
        foreach (explode("\n", $content) as $line) {
            $line = trim($line);
            // Skip comments and empty lines
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            // Parse hosts entry: IP hostname [hostname2 ...]
            $parts = preg_split('/\s+/', $line);
            if ($parts === false || count($parts) < 2) {
                continue;
            }

            // Add all hostnames (skip IP)
            for ($i = 1; $i < count($parts); $i++) {
                $entries[] = $parts[$i];
            }
        }

        return $entries;
    }

    /**
     * Remove DNS entries for a project from /etc/hosts.
     */
    public function removeHostsEntries(string $projectName): DnsConfigurationResult
    {
        if (!file_exists('/etc/hosts')) {
            return new DnsConfigurationResult(
                type: 'hosts-file',
                automatic: false,
                requiresSudo: false,
                configPath: '/etc/hosts',
                configContent: null,
                instructions: ['/etc/hosts file not found'],
                restartCommand: null,
            );
        }

        $content = file_get_contents('/etc/hosts');
        if ($content === false) {
            return new DnsConfigurationResult(
                type: 'hosts-file',
                automatic: false,
                requiresSudo: false,
                configPath: '/etc/hosts',
                configContent: null,
                instructions: ['Could not read /etc/hosts'],
                restartCommand: null,
            );
        }

        $startMarker = self::HOSTS_MARKER_START . " {$projectName}";
        $endMarker = self::HOSTS_MARKER_END . " {$projectName}";

        // Check if markers exist
        if (!str_contains($content, $startMarker)) {
            return new DnsConfigurationResult(
                type: 'hosts-file',
                automatic: false,
                requiresSudo: false,
                configPath: '/etc/hosts',
                configContent: null,
                instructions: ["No Seaman DNS entries found for project '{$projectName}'"],
                restartCommand: null,
            );
        }

        // Remove the section between markers (including markers)
        $pattern = '/\n?' . preg_quote($startMarker, '/') . '.*?' . preg_quote($endMarker, '/') . '\n?/s';
        $newContent = preg_replace($pattern, '', $content);

        if ($newContent === null || $newContent === $content) {
            return new DnsConfigurationResult(
                type: 'hosts-file',
                automatic: false,
                requiresSudo: false,
                configPath: '/etc/hosts',
                configContent: null,
                instructions: ['Could not remove DNS entries from /etc/hosts'],
                restartCommand: null,
            );
        }

        return new DnsConfigurationResult(
            type: 'hosts-file-cleanup',
            automatic: true,
            requiresSudo: true,
            configPath: '/etc/hosts',
            configContent: $newContent,
            instructions: [],
            restartCommand: null,
        );
    }

    /**
     * Clean up DNS configuration for a project based on the provider used.
     */
    public function cleanupDns(string $projectName, DnsProvider $provider): DnsConfigurationResult
    {
        return match ($provider) {
            DnsProvider::HostsFile => $this->removeHostsEntries($projectName),
            DnsProvider::Dnsmasq => $this->removeDnsmasqConfig($projectName),
            DnsProvider::SystemdResolved => $this->removeSystemdResolvedConfig($projectName),
            DnsProvider::NetworkManager => $this->removeNetworkManagerConfig($projectName),
            DnsProvider::MacOSResolver => $this->removeMacOSResolverConfig($projectName),
            DnsProvider::Manual => new DnsConfigurationResult(
                type: 'manual',
                automatic: false,
                requiresSudo: false,
                configPath: null,
                configContent: null,
                instructions: ['Manual DNS configuration - please remove entries manually'],
                restartCommand: null,
            ),
        };
    }

    /**
     * Execute DNS cleanup for a project with a known provider.
     *
     * @return array{success: bool, messages: list<string>}
     */
    public function executeDnsCleanup(string $projectName, DnsProvider $provider): array
    {
        if ($this->privilegedExecutor === null) {
            return [
                'success' => false,
                'messages' => ['PrivilegedExecutor not configured'],
            ];
        }

        $result = $this->cleanupDns($projectName, $provider);
        $messages = [];

        if (!$result->automatic) {
            return [
                'success' => true,
                'messages' => $result->instructions,
            ];
        }

        // Handle hosts file cleanup (requires writing new content)
        if ($result->type === 'hosts-file-cleanup' && $result->configContent !== null) {
            $tempFile = tempnam(sys_get_temp_dir(), 'hosts_');
            if ($tempFile === false) {
                return [
                    'success' => false,
                    'messages' => ['Failed to create temp file for hosts cleanup'],
                ];
            }

            file_put_contents($tempFile, $result->configContent);
            $copyResult = $this->privilegedExecutor->execute(['cp', $tempFile, '/etc/hosts']);
            unlink($tempFile);

            if ($copyResult->isSuccessful()) {
                return [
                    'success' => true,
                    'messages' => ['Removed DNS entries from /etc/hosts'],
                ];
            }

            $priv = $this->getPrivilegeCommand();
            return [
                'success' => false,
                'messages' => ["Failed to update /etc/hosts (requires {$priv})"],
            ];
        }

        // Handle file removal for other providers
        if ($result->configPath !== null && file_exists($result->configPath)) {
            $removeResult = $this->privilegedExecutor->execute(['rm', '-f', $result->configPath]);

            if (!$removeResult->isSuccessful()) {
                $priv = $this->getPrivilegeCommand();
                return [
                    'success' => false,
                    'messages' => ["Failed to remove {$result->configPath} (requires {$priv})"],
                ];
            }

            $messages[] = "Removed {$result->configPath}";

            // Restart service if needed
            if ($result->restartCommand !== null) {
                $parts = explode(' ', $result->restartCommand);
                // Skip privilege prefix since we use PrivilegedExecutor
                if (in_array($parts[0], ['sudo', 'pkexec'], true)) {
                    array_shift($parts);
                }
                $restartResult = $this->privilegedExecutor->execute($parts);
                if ($restartResult->isSuccessful()) {
                    $messages[] = 'Restarted DNS service';
                }
            }

            return [
                'success' => true,
                'messages' => $messages,
            ];
        }

        return [
            'success' => true,
            'messages' => ['No DNS configuration to clean'],
        ];
    }

    /**
     * Execute DNS cleanup by scanning for all possible config files.
     * Used when the provider is unknown.
     *
     * @return array{success: bool, messages: list<string>}
     */
    public function executeDnsCleanupAll(string $projectName): array
    {
        if ($this->privilegedExecutor === null) {
            return [
                'success' => false,
                'messages' => ['PrivilegedExecutor not configured'],
            ];
        }

        $messages = [];
        $removedAny = false;

        // Get all possible config paths
        $configPaths = $this->getAllDnsConfigPaths($projectName);

        foreach ($configPaths as $configPath) {
            if (file_exists($configPath)) {
                $result = $this->privilegedExecutor->execute(['rm', '-f', $configPath]);
                if ($result->isSuccessful()) {
                    $messages[] = "Removed {$configPath}";
                    $removedAny = true;
                } else {
                    $priv = $this->getPrivilegeCommand();
                    $messages[] = "Failed to remove {$configPath} (requires {$priv})";
                }
            }
        }

        // Also check /etc/hosts for Seaman entries
        $hostsResult = $this->removeHostsEntries($projectName);
        if ($hostsResult->type === 'hosts-file-cleanup' && $hostsResult->configContent !== null) {
            $tempFile = tempnam(sys_get_temp_dir(), 'hosts_');
            if ($tempFile !== false) {
                file_put_contents($tempFile, $hostsResult->configContent);
                $copyResult = $this->privilegedExecutor->execute(['cp', $tempFile, '/etc/hosts']);
                unlink($tempFile);

                if ($copyResult->isSuccessful()) {
                    $messages[] = 'Removed DNS entries from /etc/hosts';
                    $removedAny = true;
                }
            }
        }

        if ($removedAny) {
            $this->restartDnsServices();
            $messages[] = 'DNS configuration cleaned up';
        }

        if (empty($messages)) {
            $messages[] = 'No DNS configuration files found';
        }

        return [
            'success' => true,
            'messages' => $messages,
        ];
    }

    /**
     * Get all possible DNS config paths for a project.
     *
     * @return list<string>
     */
    private function getAllDnsConfigPaths(string $projectName): array
    {
        $paths = [];

        // dnsmasq paths
        if (PHP_OS_FAMILY === 'Linux') {
            $paths[] = "/etc/dnsmasq.d/seaman-{$projectName}.conf";
        } elseif (PHP_OS_FAMILY === 'Darwin') {
            $paths[] = "/usr/local/etc/dnsmasq.d/seaman-{$projectName}.conf";
            $paths[] = "/etc/resolver/{$projectName}.local";
        }

        // systemd-resolved path
        $paths[] = "/etc/systemd/resolved.conf.d/seaman-{$projectName}.conf";

        // NetworkManager path
        $paths[] = "/etc/NetworkManager/dnsmasq.d/seaman-{$projectName}.conf";

        return $paths;
    }

    /**
     * Restart DNS services after cleanup.
     */
    private function restartDnsServices(): void
    {
        if ($this->privilegedExecutor === null) {
            return;
        }

        // Try to restart dnsmasq
        $result = $this->executor->execute(['which', 'dnsmasq']);
        if ($result->isSuccessful()) {
            if (PHP_OS_FAMILY === 'Darwin') {
                $this->privilegedExecutor->execute(['brew', 'services', 'restart', 'dnsmasq']);
            } else {
                $this->privilegedExecutor->execute(['systemctl', 'restart', 'dnsmasq']);
            }
            return;
        }

        // Try to restart systemd-resolved
        $result = $this->executor->execute(['systemctl', 'is-active', 'systemd-resolved']);
        if ($result->isSuccessful()) {
            $this->privilegedExecutor->execute(['systemctl', 'restart', 'systemd-resolved']);
        }
    }

    /**
     * Remove dnsmasq configuration for a project.
     */
    private function removeDnsmasqConfig(string $projectName): DnsConfigurationResult
    {
        $configPath = $this->getDnsmasqConfigPath($projectName);

        if (!file_exists($configPath)) {
            return new DnsConfigurationResult(
                type: 'dnsmasq-cleanup',
                automatic: false,
                requiresSudo: false,
                configPath: $configPath,
                configContent: null,
                instructions: ["No dnsmasq config found at {$configPath}"],
                restartCommand: null,
            );
        }

        $priv = $this->getPrivilegeCommand();
        $restartCommand = PHP_OS_FAMILY === 'Darwin'
            ? "{$priv} brew services restart dnsmasq"
            : "{$priv} systemctl restart dnsmasq";

        return new DnsConfigurationResult(
            type: 'dnsmasq-cleanup',
            automatic: true,
            requiresSudo: true,
            configPath: $configPath,
            configContent: null,
            instructions: [],
            restartCommand: $restartCommand,
        );
    }

    /**
     * Remove systemd-resolved configuration for a project.
     */
    private function removeSystemdResolvedConfig(string $projectName): DnsConfigurationResult
    {
        $configPath = "/etc/systemd/resolved.conf.d/seaman-{$projectName}.conf";

        if (!file_exists($configPath)) {
            return new DnsConfigurationResult(
                type: 'systemd-resolved-cleanup',
                automatic: false,
                requiresSudo: false,
                configPath: $configPath,
                configContent: null,
                instructions: ["No systemd-resolved config found at {$configPath}"],
                restartCommand: null,
            );
        }

        $priv = $this->getPrivilegeCommand();

        return new DnsConfigurationResult(
            type: 'systemd-resolved-cleanup',
            automatic: true,
            requiresSudo: true,
            configPath: $configPath,
            configContent: null,
            instructions: [],
            restartCommand: "{$priv} systemctl restart systemd-resolved",
        );
    }

    /**
     * Remove NetworkManager dnsmasq configuration for a project.
     */
    private function removeNetworkManagerConfig(string $projectName): DnsConfigurationResult
    {
        $configPath = "/etc/NetworkManager/dnsmasq.d/seaman-{$projectName}.conf";

        if (!file_exists($configPath)) {
            return new DnsConfigurationResult(
                type: 'networkmanager-cleanup',
                automatic: false,
                requiresSudo: false,
                configPath: $configPath,
                configContent: null,
                instructions: ["No NetworkManager config found at {$configPath}"],
                restartCommand: null,
            );
        }

        $priv = $this->getPrivilegeCommand();

        return new DnsConfigurationResult(
            type: 'networkmanager-cleanup',
            automatic: true,
            requiresSudo: true,
            configPath: $configPath,
            configContent: null,
            instructions: [],
            restartCommand: "{$priv} systemctl restart NetworkManager",
        );
    }

    /**
     * Remove macOS resolver configuration for a project.
     */
    private function removeMacOSResolverConfig(string $projectName): DnsConfigurationResult
    {
        $configPath = "/etc/resolver/{$projectName}.local";

        if (!file_exists($configPath)) {
            return new DnsConfigurationResult(
                type: 'macos-resolver-cleanup',
                automatic: false,
                requiresSudo: false,
                configPath: $configPath,
                configContent: null,
                instructions: ["No macOS resolver config found at {$configPath}"],
                restartCommand: null,
            );
        }

        return new DnsConfigurationResult(
            type: 'macos-resolver-cleanup',
            automatic: true,
            requiresSudo: true,
            configPath: $configPath,
            configContent: null,
            instructions: [],
            restartCommand: null,
        );
    }

    /**
     * Get manual instructions for DNS configuration.
     */
    private function getManualInstructions(string $projectName, ?ServiceCollection $services = null): DnsConfigurationResult
    {
        $hostsEntries = $this->getHostsEntries($projectName, $services);

        $instructions = [
            'Add the following entries to /etc/hosts:',
            '',
        ];

        foreach ($hostsEntries as $host) {
            $instructions[] = "127.0.0.1 {$host}";
        }

        $instructions = array_merge($instructions, [
            '',
            'Or configure dnsmasq for wildcard domain support:',
            '',
            'On systems with systemd-resolved blocking port 53:',
            '  1. Disable stub listener: sudo mkdir -p /etc/systemd/resolved.conf.d',
            "  2. Create config: echo -e '[Resolve]\\nDNSStubListener=no' | sudo tee /etc/systemd/resolved.conf.d/no-stub.conf",
            '  3. Restart resolved: sudo systemctl restart systemd-resolved',
            '  4. Start dnsmasq: sudo systemctl enable --now dnsmasq',
            '',
            'Or use NetworkManager with dnsmasq:',
            "  1. Add 'dns=dnsmasq' to [main] section in /etc/NetworkManager/NetworkManager.conf",
            '  2. Restart NetworkManager: sudo systemctl restart NetworkManager',
        ]);

        return new DnsConfigurationResult(
            type: 'manual',
            automatic: false,
            requiresSudo: false,
            configPath: null,
            configContent: null,
            instructions: $instructions,
            restartCommand: null,
        );
    }

    /**
     * Get dnsmasq configuration path based on platform.
     */
    private function getDnsmasqConfigPath(string $projectName): string
    {
        return match (PHP_OS_FAMILY) {
            'Linux' => "/etc/dnsmasq.d/seaman-{$projectName}.conf",
            'Darwin' => "/usr/local/etc/dnsmasq.d/seaman-{$projectName}.conf",
            default => throw new \RuntimeException('Unsupported platform: ' . PHP_OS_FAMILY),
        };
    }
}
