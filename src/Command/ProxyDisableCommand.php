<?php

declare(strict_types=1);

// ABOUTME: Command to disable Traefik reverse proxy.
// ABOUTME: Regenerates docker-compose with direct port access.

namespace Seaman\Command;

use Seaman\Command\Concern\RegeneratesDockerCompose;
use Seaman\Enum\OperatingMode;
use Seaman\Service\ConfigManager;
use Seaman\UI\Terminal;
use Seaman\ValueObject\ProxyConfig;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'proxy:disable',
    description: 'Disable Traefik reverse proxy',
    aliases: ['proxy:disable'],
)]
class ProxyDisableCommand extends ModeAwareCommand
{
    use RegeneratesDockerCompose;

    public function __construct(
        private readonly ConfigManager $configManager,
    ) {
        parent::__construct();
    }

    public function supportsMode(OperatingMode $mode): bool
    {
        return $mode === OperatingMode::Managed;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $projectRoot = (string) getcwd();

        try {
            $config = $this->configManager->load();
        } catch (\RuntimeException $e) {
            Terminal::error('Failed to load configuration: ' . $e->getMessage());
            return Command::FAILURE;
        }

        if (!$config->proxy()->enabled) {
            Terminal::output()->writeln('  <fg=yellow>ℹ</> Proxy is already disabled.');
            return Command::SUCCESS;
        }

        $newConfig = $config->with(proxy: ProxyConfig::disabled());

        $this->regenerateDockerCompose($newConfig, $projectRoot);

        $this->configManager->save($newConfig);

        Terminal::success('Proxy disabled successfully');
        Terminal::output()->writeln('');
        Terminal::output()->writeln("  Run 'seaman restart' to apply changes.");
        Terminal::output()->writeln('');
        Terminal::output()->writeln('  Your services will be accessible at:');
        Terminal::output()->writeln('  • http://localhost:80 (app)');

        return Command::SUCCESS;
    }
}
