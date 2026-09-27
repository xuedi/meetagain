<?php declare(strict_types=1);

namespace Module\Email\Internal;

use DateTimeImmutable;
use Module\Email\Contract\BlocklistInterface;
use Module\Email\Contract\EmailInterface;
use Module\Email\Contract\GuardOutcome;
use Module\Email\Contract\GuardResult;
use Module\Email\Contract\MailerInterface;
use Module\Email\Contract\SendOutcome;

readonly class Mailer implements MailerInterface
{
    public function __construct(
        private GuardEvaluator $guardEvaluator,
        private EmailQueueInterface $queue,
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
        return $this->guardEvaluator->evaluate($type, $context);
    }

    public function dispatchPush(string $identifier, string $recipient, ?DateTimeImmutable $deadline): void
    {
        $this->queue->dispatchPush($identifier, $recipient, $deadline);
    }
}
