<?php declare(strict_types=1);

namespace Module\Email\Internal;

use DateTimeImmutable;
use Module\Email\Contract\EmailInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;

interface EmailQueueInterface
{
    /**
     * A non-null $origin replaces what the source reports, for callers that know better than the
     * type does. $dispatchPush is false for preview and debugging paths, which enqueue a real row
     * for a message nothing actually happened about.
     */
    public function enqueue(
        EmailInterface $source,
        TemplatedEmail $email,
        array $context,
        bool $flush = true,
        ?object $origin = null,
        bool $dispatchPush = true,
    ): bool;

    public function dispatchPush(string $identifier, string $recipient, ?DateTimeImmutable $deadline): void;
}
