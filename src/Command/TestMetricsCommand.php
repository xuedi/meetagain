<?php declare(strict_types=1);

namespace App\Command;

use Override;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:test:metrics',
    description: 'Analyze Symfony route performance metrics (queries, timing, memory)',
)]
class TestMetricsCommand extends Command
{
    #[Override]
    protected function configure(): void
    {
        $this
            ->addOption('threshold', null, InputOption::VALUE_REQUIRED, 'Show only routes with >N queries', '20')
            ->addOption(
                'sort',
                null,
                InputOption::VALUE_REQUIRED,
                'Sort by: queries (default), time, or memory',
                'queries',
            )
            ->addOption('filter', null, InputOption::VALUE_REQUIRED, 'Filter routes by name pattern')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Output as JSON');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $threshold = (int) $input->getOption('threshold');
        $sortBy = $input->getOption('sort');
        $filter = $input->getOption('filter');
        $json = $input->getOption('json');

        // For now, show a placeholder message
        $output->writeln('');
        $output->writeln('ROUTE METRICS ANALYSIS');
        $output->writeln('===');
        $output->writeln('');
        $output->writeln('Note: Route metrics analysis requires running functional tests.');
        $output->writeln('This command analyzes route performance during test execution.');
        $output->writeln('');
        $output->writeln('Options:');
        $output->writeln('  --threshold=N   Show only routes with >N queries (default: 20)');
        $output->writeln('  --sort=FIELD    Sort by: queries (default), time, or memory');
        $output->writeln('  --filter=STR    Filter routes by name pattern');
        $output->writeln('  --json          Output as JSON');
        $output->writeln('');
        $output->writeln('Example:');
        $output->writeln('  just app app:test:metrics --threshold=50 --sort=time');
        $output->writeln('');

        return Command::SUCCESS;
    }
}
