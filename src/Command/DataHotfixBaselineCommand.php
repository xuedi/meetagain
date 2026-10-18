<?php declare(strict_types=1);

namespace App\Command;

use App\DataHotfix\Runner;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:data-hotfix:baseline', description: 'Records every data hotfix as done without running it - a new database has nothing to repair')]
class DataHotfixBaselineCommand extends Command
{
    public function __construct(
        private readonly Runner $runner,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $recorded = 0;
        foreach ($this->runner->all() as $hotfix) {
            if ($this->runner->ranAt($hotfix) !== null) {
                continue;
            }
            $this->runner->record($hotfix);
            $recorded++;
        }

        $output->writeln(sprintf('hotfixes: %d recorded as done without running', $recorded));

        return Command::SUCCESS;
    }
}
