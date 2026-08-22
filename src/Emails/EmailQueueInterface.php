<?php declare(strict_types=1);

namespace App\Emails;

use Symfony\Bridge\Twig\Mime\TemplatedEmail;

interface EmailQueueInterface
{
    /**
     * A non-null $origin replaces what the source reports, for callers that know better than the
     * type does.
     */
    public function enqueue(
        EmailInterface $source,
        TemplatedEmail $email,
        array $context,
        bool $flush = true,
        ?object $origin = null,
    ): bool;
}
