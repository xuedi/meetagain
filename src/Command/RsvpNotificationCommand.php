<?php declare(strict_types=1);

namespace App\Command;

use App\Service\RsvpNotificationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:rsvp:notify',
    description: 'Send notifications to users whose followed users have RSVPd to upcoming events',
)]
class RsvpNotificationCommand extends Command
{
    public function __construct(
        private readonly RsvpNotificationService $rsvpNotificationService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('days', 'd', InputOption::VALUE_OPTIONAL, 'How many days ahead to check for events', 7);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $days = (int) $input->getOption('days');

        $io->info(sprintf('Processing upcoming events for the next %d days...', $days));

        $count = $this->rsvpNotificationService->processUpcomingEvents($days);

        $io->success(sprintf('Sent %d notification emails.', $count));

        return Command::SUCCESS;
    }
}
