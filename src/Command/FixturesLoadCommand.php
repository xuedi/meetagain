<?php declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:fixtures:load', description: 'Load data fixtures with graceful handling of empty groups')]
class FixturesLoadCommand extends Command
{
    public function __construct(
        private readonly FixturesLoaderInterface $fixturesLoader,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'append',
                null,
                InputOption::VALUE_NONE,
                'Append the data fixtures instead of deleting all data from the database first.',
            )
            ->addOption(
                'group',
                null,
                InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED,
                'Only load fixtures that belong to this group',
            )
            ->addOption('em', null, InputOption::VALUE_REQUIRED, 'The entity manager to use for this command.')
            ->addOption(
                'purge-exclusions',
                null,
                InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED,
                'List of database tables to ignore while purging',
            )
            ->addOption(
                'purge-with-truncate',
                null,
                InputOption::VALUE_NONE,
                'Purge data by using a database-level TRUNCATE statement',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $groups = $input->getOption('group');

        // Check if any fixtures exist for the specified groups
        $fixtures = $this->fixturesLoader->getFixtures($groups);

        if (empty($fixtures)) {
            if ($output->isVerbose() === false) {
                $groupText = empty($groups) ? 'all groups' : implode(', ', $groups);
                $output->writeln(sprintf('No fixtures found for %s. Skipping.', $groupText));
            }
            return Command::SUCCESS;
        }

        // Delegate to the doctrine:fixtures:load command
        $doctrineCommand = $this->getApplication()->find('doctrine:fixtures:load');

        $arguments = [
            'command' => 'doctrine:fixtures:load',
            '--no-interaction' => true,
        ];

        if ($input->getOption('append')) {
            $arguments['--append'] = true;
        }

        if (!empty($groups)) {
            $arguments['--group'] = $groups;
        }

        if ($input->getOption('em')) {
            $arguments['--em'] = $input->getOption('em');
        }

        if ($input->getOption('purge-exclusions')) {
            $arguments['--purge-exclusions'] = $input->getOption('purge-exclusions');
        }

        if ($input->getOption('purge-with-truncate')) {
            $arguments['--purge-with-truncate'] = true;
        }

        if ($output->isQuiet()) {
            $arguments['--quiet'] = true;
        }

        return $doctrineCommand->run(new ArrayInput($arguments), $output);
    }
}
