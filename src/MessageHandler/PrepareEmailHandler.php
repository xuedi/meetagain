<?php declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\EmailType\ResetPassword;
use App\Entity\EmailType\VerificationRequest;
use App\Entity\EmailType\Welcome;
use App\Message\PrepareEmail;
use App\Message\SendEmail;
use App\Service\EmailService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
readonly class PrepareEmailHandler
{
    public function __construct(
        private EmailService $emailService,
        private MessageBusInterface $messageBus,
    )
    {
    }

    public function __invoke(PrepareEmail $emailType): void
    {
        $params = $emailType->getType();
        switch (true) {
            case $params instanceof VerificationRequest:
                $this->emailService->prepareVerificationRequest($params->getUser());
                break;
            case $params instanceof Welcome:
                $this->emailService->prepareWelcome($params->getUser());
                break;
            case $params instanceof ResetPassword:
                $this->emailService->prepareResetPassword($params->getUser());
                break;
        }

        $this->messageBus->dispatch(new SendEmail());
    }
}
