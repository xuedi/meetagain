<?php declare(strict_types=1);

namespace Module\Email\Contract;

use DateTimeImmutable;

interface MailerInterface
{
    /**
     * Runs the type's guard chain, composes the messages when it passes, and queues every message whose
     * recipient is not blocklisted. A guard Error is reported in the outcome, never thrown.
     */
    public function send(EmailInterface $type, array $context, bool $flush = true): SendOutcome;

    public function evaluate(EmailInterface $type, array $context): GuardResult;

    /**
     * Hands a push notification to every registered dispatcher without queueing a message. A dispatcher that
     * throws is logged and skipped.
     */
    public function dispatchPush(string $identifier, string $recipient, ?DateTimeImmutable $deadline): void;
}
