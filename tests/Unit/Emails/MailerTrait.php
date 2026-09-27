<?php declare(strict_types=1);

namespace Tests\Unit\Emails;

use DateTimeImmutable;
use Module\Email\Contract\BlocklistInterface;
use Module\Email\Contract\EmailInterface;
use Module\Email\Contract\GuardOutcome;
use Module\Email\Contract\GuardResult;
use Module\Email\Contract\MailerInterface;
use Module\Email\Contract\SendOutcome;

trait MailerTrait
{
    private function mailer(QueueSpy $queue, ?BlocklistInterface $blocklist = null): MailerInterface
    {
        return new readonly class($queue, $blocklist ?? $this->createStub(BlocklistInterface::class)) implements MailerInterface {
            public function __construct(
                private QueueSpy $queue,
                private BlocklistInterface $blocklist,
            ) {}

            public function send(EmailInterface $type, array $context, bool $flush = true): SendOutcome
            {
                $guard = $this->evaluate($type, $context);
                if ($guard->outcome !== GuardOutcome::Pass) {
                    return new SendOutcome($guard);
                }

                $queued = 0;
                foreach ($type->compose($context) as $email) {
                    if ($this->blocklist->isBlocked($email->getTo()[0]->getAddress())) {
                        continue;
                    }

                    $this->queue->enqueue($type, $email, $context, $flush, dispatchPush: $type->pushOnEnqueue());
                    ++$queued;
                }

                return new SendOutcome($guard, $queued);
            }

            public function evaluate(EmailInterface $type, array $context): GuardResult
            {
                foreach ($type->getGuardRules() as $rule) {
                    $result = $rule->evaluate($context);
                    if ($result->outcome !== GuardOutcome::Pass) {
                        return $result;
                    }
                }

                return GuardResult::pass('chain.all');
            }

            public function dispatchPush(string $identifier, string $recipient, ?DateTimeImmutable $deadline): void {}
        };
    }
}
