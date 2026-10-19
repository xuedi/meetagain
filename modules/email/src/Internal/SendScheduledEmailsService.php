<?php declare(strict_types=1);

namespace Module\Email\Internal;

use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Module\Email\Contract\GuardOutcome;
use Module\Email\Contract\MailerInterface;
use Module\Email\Contract\ScheduledEmailInterface;
use Module\Task\Contract\Cadence;
use Module\Task\Contract\Result;
use Module\Task\Contract\Status;
use Module\Task\Contract\TaskInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Throwable;

final readonly class SendScheduledEmailsService implements TaskInterface
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
        private EmailQueueInterface $queue,
    ) {}

    public function getIdentifier(): string
    {
        return 'scheduled-emails';
    }

    public function getCadence(): Cadence
    {
        return new Cadence('*/5 7-21 * * *', offloaded: true);
    }

    public function getDescription(): string
    {
        return 'Queues the emails that go out on a schedule rather than in response to an action, such as event reminders and the upcoming-events digest. Each scheduled email type reports which mailings are due; this task queues one mail per recipient after the usual guard rules and marks the mailing as sent so it is never repeated. It runs every five minutes from 07:00 to 21:00 so nobody is mailed at night, and the email-queue task delivers what it queued.';
    }

    public function run(): Result
    {
        try {
            $now = $this->clock->now();
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
                                    'caller' => 'SendScheduledEmailsService::run',
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
                                    'caller' => 'SendScheduledEmailsService::run',
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
                            $this->queue->wake();
                            $unflushed = 0;
                        }
                    }
                    $this->em->flush();
                    $this->queue->wake();
                    $unflushed = 0;
                    $email->markContextSent($dueContext);
                    $totalSent += $sent;
                }
            }

            $message = $totalSent . ' emails queued';

            return new Result(Status::ok, $message);
        } catch (Throwable $e) {
            return new Result(Status::exception, $e->getMessage());
        }
    }
}
