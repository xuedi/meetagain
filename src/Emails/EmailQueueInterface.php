<?php declare(strict_types=1);

namespace App\Emails;

use Symfony\Bridge\Twig\Mime\TemplatedEmail;

interface EmailQueueInterface
{
    public function enqueue(EmailInterface $source, TemplatedEmail $email, array $context, bool $flush = true): bool;
}
