<?php declare(strict_types=1);

namespace App\Command;

use App\Service\EventService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:event:extent', description: 'extent all recurring events',)]
class EventExtentCommand extends Command
{
    public function __construct(private readonly EventService $eventService)
    {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->eventService->extentRecurringEvents();

        return Command::SUCCESS;
    }
}
