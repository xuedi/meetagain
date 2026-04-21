<?php declare(strict_types=1);

namespace App\Emails;

use App\Enum\EmailType;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;

interface EmailQueueInterface
{
    public function enqueue(
        EmailInterface $source,
        TemplatedEmail $email,
        EmailType $type,
        array $context,
        bool $flush = true,
    ): bool;
}
