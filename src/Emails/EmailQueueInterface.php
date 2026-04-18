<?php declare(strict_types=1);

namespace App\Emails;

use App\Enum\EmailType;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;

interface EmailQueueInterface
{
    public function enqueue(TemplatedEmail $email, EmailType $type, bool $flush = true): bool;
}
