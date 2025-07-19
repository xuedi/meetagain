<?php declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:echo', description: 'simple command for testing, echos parameter',)]
class EchoCommand extends Command
{
    const string PARAMETER_MESSAGE = 'message';

    #[\Override]
    protected function configure(): void
    {
        $this->addArgument(self::PARAMETER_MESSAGE, InputArgument::REQUIRED, 'Message to return');
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->write('Echo command: ');
        $output->writeln($input->getArgument(self::PARAMETER_MESSAGE));

        return Command::SUCCESS;
    }
}
