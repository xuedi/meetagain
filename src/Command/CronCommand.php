<?php declare(strict_types=1);

namespace App\Command;

use App\Service\ActivityService;
use App\Service\CommandExecutionService;
use App\Service\EmailService;
use App\Service\RsvpNotificationService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:cron', description: 'cron manager to be called often, maybe every 5 min or so')]
class CronCommand extends LoggedCommand
{
    use LockableTrait;

    public function __construct(
        private readonly EmailService $mailService,
        private readonly ActivityService $activityService,
        private readonly RsvpNotificationService $rsvpNotificationService,
        private readonly LoggerInterface $logger,
        CommandExecutionService $commandExecutionService,
    ) {
        parent::__construct($commandExecutionService);
    }

    protected function doExecute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->lock()) {
            return Command::SUCCESS;
        }

        $output->write('Send out queued emails ... ');
        $this->mailService->sendQueue();
        $output->writeln('OK');

        $output->write('Send RSVP notifications ... ');
        $count = $this->rsvpNotificationService->processUpcomingEvents(7);
        $output->writeln(sprintf('%d sent', $count));

        $output->write('Validating activity payloads ... ');
        $invalidActivities = $this->activityService->validateAllActivities();
        if ($invalidActivities === []) {
            $output->writeln('OK');
        } else {
            $output->writeln(sprintf('<error>%d invalid activities found</error>', count($invalidActivities)));
            foreach ($invalidActivities as $invalid) {
                $this->logger->warning('Invalid activity payload', [
                    'activity_id' => $invalid['id'],
                    'type' => $invalid['type'],
                    'error' => $invalid['error'],
                ]);
                $output->writeln(sprintf(
                    '  - Activity #%d (%s): %s',
                    $invalid['id'],
                    $invalid['type'],
                    $invalid['error']
                ));
            }
        }

        $this->release();

        return Command::SUCCESS;
    }
}
