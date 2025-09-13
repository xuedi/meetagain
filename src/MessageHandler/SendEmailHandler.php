<?php declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\SendEmail;
use App\Service\EmailService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class SendEmailHandler
{
    public function __construct(private EmailService $emailService)
    {
    }

    public function __invoke(SendEmail $emailType): void
    {
        $this->emailService->sendQueue();
    }
}
