<?php declare(strict_types=1);

namespace Tests\Unit\Emails;

use Module\Email\Contract\EmailInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;

interface QueueSpy
{
    public function enqueue(
        EmailInterface $source,
        TemplatedEmail $email,
        array $context,
        bool $flush = true,
        ?object $origin = null,
        bool $dispatchPush = true,
    ): bool;
}
