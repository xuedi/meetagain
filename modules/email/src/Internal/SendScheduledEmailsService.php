<?php declare(strict_types=1);

namespace Module\Email\Internal;

use App\CronTaskInterface;
use App\Enum\CronTaskStatus;
use App\ValueObject\CronTaskResult;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Module\Email\Contract\GuardOutcome;
use Module\Email\Contract\MailerInterface;
use Module\Email\Contract\ScheduledEmailInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Throwable;

final readonly class SendScheduledEmailsService implements CronTaskInterface
{
    private const int FLUSH_EVERY = 200;

    /**
     * @param iterable<ScheduledEmailInterface> $scheduledEmails
     */
    public function __construct(
        #[AutowireIterator(ScheduledEmailInterface::class)]
        private iterable $scheduledEmails,
        private ClockInterface $clock,
        private LoggerInterface $logger,
        private MailerInterface $mailer,
        private EntityManagerInterface $em,
    ) {}

    public function getIdentifier(): string
    {
        return 'scheduled-emails';
    }

    public function runCronTask(OutputInterface $output): CronTaskResult
    {
        try {
            $now = $this->clock->now();
            $currentHour = (int) $now->format('H');

            if ($currentHour < 7 || $currentHour >= 22) {
                $message = 'skipped: outside allowed hours (07:00-22:00)';
                $output->writeln('SendScheduledEmailsService: ' . $message);

                return new CronTaskResult($this->getIdentifier(), CronTaskStatus::ok, $message);
            }

            $totalSent = 0;
            $unflushed = 0;
            $loggedGuardErrors = [];

            foreach ($this->scheduledEmails as $email) {
                foreach ($email->getDueContexts($now) as $dueContext) {
                    $sent = 0;
                    foreach ($dueContext->potentialRecipients as $user) {
                        $ctx = array_merge($dueContext->data, ['user' => $user]);
                        try {
                            $outcome = $this->mailer->send($email, $ctx, flush: false);
                        } catch (InvalidArgumentException $e) {
                            $dedupKey = $email->getIdentifier() . ':' . $e->getMessage();
                            if (!isset($loggedGuardErrors[$dedupKey])) {
                                $loggedGuardErrors[$dedupKey] = true;
                                $this->logger->error('guard rule threw - email skipped', [
                                    'email' => $email->getIdentifier(),
                                    'caller' => 'SendScheduledEmailsService::runCronTask',
                                    'context_keys' => array_keys($ctx),
                                    'exception' => $e->getMessage(),
                                ]);
                            }
                            continue;
                        }

                        $result = $outcome->guard;
                        if ($result->outcome === GuardOutcome::Error) {
                            $dedupKey = $email->getIdentifier() . ':' . $result->ruleName;
                            if (!isset($loggedGuardErrors[$dedupKey])) {
                                $loggedGuardErrors[$dedupKey] = true;
                                $this->logger->error('guard rule returned Error - email skipped', [
                                    'email' => $email->getIdentifier(),
                                    'caller' => 'SendScheduledEmailsService::runCronTask',
                                    'rule' => $result->ruleName,
                                    'explanation' => $result->explanation,
                                    'context_key' => $result->contextKey,
                                ]);
                            }
                            continue;
                        }

                        $sent += $outcome->queued;
                        $unflushed += $outcome->queued;
                        if ($unflushed >= self::FLUSH_EVERY) {
                            $this->em->flush();
                            $unflushed = 0;
                        }
                    }
                    $this->em->flush();
                    $unflushed = 0;
                    $email->markContextSent($dueContext);
                    $totalSent += $sent;
                }
            }

            $message = $totalSent . ' emails queued';
            $output->writeln('SendScheduledEmailsService: ' . $message);

            return new CronTaskResult($this->getIdentifier(), CronTaskStatus::ok, $message);
        } catch (Throwable $e) {
            $output->writeln('SendScheduledEmailsService exception: ' . $e->getMessage());

            return new CronTaskResult($this->getIdentifier(), CronTaskStatus::exception, $e->getMessage());
        }
    }
}
