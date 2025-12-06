<?php

declare(strict_types=1);

// ABOUTME: Executes commands inside the app container.
// ABOUTME: Unified command for composer, console, and php execution.

namespace Seaman\Command;

use Seaman\Contract\Decorable;
use Seaman\Service\DockerManager;
use Seaman\UI\Terminal;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ExecuteCommand extends ModeAwareCommand implements Decorable
{
    /**
     * @param list<string> $aliases
     * @param list<string> $commandPrefix
     */
    public function __construct(
        string $name,
        private readonly string $commandDescription,
        private readonly array $aliases,
        private readonly array $commandPrefix,
        private readonly DockerManager $dockerManager,
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setDescription($this->commandDescription);
        $this->setAliases($this->aliases);
        $this->ignoreValidationErrors();
        $this->addArgument('args', InputArgument::IS_ARRAY, 'Command arguments');
    }

    public function supportsMode(\Seaman\Enum\OperatingMode $mode): bool
    {
        return true;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var list<string> $args */
        $args = $input->getArgument('args');

        $command = [...$this->commandPrefix, ...$args];

        $result = $this->dockerManager->executeInService('app', $command);
        Terminal::output()->writeln($result->output);

        if (!$result->isSuccessful()) {
            Terminal::error($result->output);
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
