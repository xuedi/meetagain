<?php declare(strict_types=1);

namespace Module\Task\Internal\Command;

use Module\Task\Contract\Status;
use Module\Task\Internal\Runner;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:task:run', description: 'Runs one scheduled task now and records the run')]
class RunCommand extends Command
{
    public function __construct(
        private readonly Runner $runner,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this->addArgument('identifier', InputArgument::REQUIRED, 'The task identifier, as app:task:list shows it');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $result = $this->runner->run((string) $input->getArgument('identifier'));
        $output->writeln(sprintf('%s: %s', $result->status->value, $result->message));

        return in_array($result->status, [Status::ok, Status::warning], true) ? Command::SUCCESS : Command::FAILURE;
    }
}
