<?php

declare(strict_types=1);

// ABOUTME: Interactive initialization command.
// ABOUTME: Creates seaman.yaml and sets up Docker environment.

namespace Seaman\Command;

use Seaman\Contracts\Decorable;
use Seaman\Enum\ProjectType;
use Seaman\Service\ConfigurationFactory;
use Seaman\Service\InitializationSummary;
use Seaman\Service\InitializationWizard;
use Seaman\Service\ProjectBootstrapper;
use Seaman\Service\ProjectInitializer;
use Seaman\Service\SymfonyDetector;
use Seaman\UI\Terminal;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;

#[AsCommand(
    name: 'seaman:init',
    description: 'Initialize Seaman configuration interactively',
    aliases: ['init'],
)]
class InitCommand extends AbstractSeamanCommand implements Decorable
{
    public function __construct(
        private readonly SymfonyDetector $detector,
        private readonly ProjectBootstrapper $bootstrapper,
        private readonly ConfigurationFactory $configFactory,
        private readonly InitializationSummary $summary,
        private readonly InitializationWizard $wizard,
        private readonly ProjectInitializer $initializer,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'with-devcontainer',
            null,
            InputOption::VALUE_NONE,
            'Generate DevContainer configuration for VS Code',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $projectRoot = (string) getcwd();

        // Check if seaman.yaml already exists
        if (file_exists($projectRoot . '/.seaman/seaman.yaml')) {
            if (!confirm(
                label: 'seaman.yaml already exists. Overwrite?',
                default: false,
            )) {
                info('Initialization cancelled.');
                return Command::SUCCESS;
            }
        }

        // Bootstrap Symfony project if needed
        $projectType = $this->bootstrapSymfonyProject($projectRoot);

        // Run initialization wizard to collect all choices
        $choices = $this->wizard->run($input, $projectType, $projectRoot);

        // Build configuration from choices
        $config = $this->configFactory->createFromChoices($choices, $projectType);

        // Show configuration summary
        $this->summary->display(
            database: $choices->database,
            services: $choices->services,
            phpConfig: $config->php,
            projectType: $projectType,
        );

        if (!confirm(label: 'Continue with this configuration?')) {
            Terminal::success('Initialization cancelled.');
            return Command::SUCCESS;
        }

        // Initialize Docker environment
        $this->initializer->initializeDockerEnvironment($config, $projectRoot);

        // Generate DevContainer if requested
        if ($choices->generateDevContainer) {
            $this->initializer->generateDevContainer($projectRoot);
        }

        Terminal::success('Seaman initialized successfully');
        Terminal::output()->writeln([
            '',
            '  Run \'seaman start\' to start your containers',
            '',
            '  ❤️ Happy coding!',

        ]);

        return Command::SUCCESS;
    }

    private function bootstrapSymfonyProject(string $projectRoot): ProjectType
    {
        $detection = $this->detector->detect($projectRoot);
        if (!$detection->isSymfonyProject) {
            $shouldBootstrap = confirm(
                label: 'No Symfony application detected. Create new project?',
            );

            if (!$shouldBootstrap) {
                info('Please create a Symfony project first, then run init again.');
                exit(Command::FAILURE);
            }

            // Bootstrap new Symfony project
            $projectType = $this->wizard->selectProjectType();
            $projectName = $this->wizard->getProjectName($projectRoot);

            info('Creating Symfony project...');

            if (!$this->bootstrapper->bootstrap($projectType, $projectName, dirname($projectRoot))) {
                info('Failed to create Symfony project.');
                exit(Command::FAILURE);
            }

            // Change to new project directory
            $projectRoot = dirname($projectRoot) . '/' . $projectName;
            chdir($projectRoot);

            info('Symfony project created successfully!');
            return $projectType;
        }

        return ProjectType::Existing;
    }
}
