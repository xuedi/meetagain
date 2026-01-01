<?php declare(strict_types=1);

namespace App\Service;

use App\Entity\EmailQueue;
use App\Entity\EmailTemplate;
use App\Entity\Event;
use App\Entity\User;
use App\Enum\EmailType;
use App\Repository\EmailQueueRepository;
use DateTime;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;

readonly class EmailService
{
    public function __construct(
        private MailerInterface $mailer,
        private ConfigService $config,
        private EmailQueueRepository $mailRepo,
        private EntityManagerInterface $em,
        private EmailTemplateService $templateService,
    ) {
    }

    public function prepareVerificationRequest(User $user): bool
    {
        $email = new TemplatedEmail();
        $email->from($this->config->getMailerAddress());
        $email->to((string) $user->getEmail());
        $email->locale($user->getLocale());
        $email->context([
            'host' => $this->config->getHost(),
            'token' => $user->getRegcode(),
            'url' => $this->config->getUrl(),
            'username' => $user->getName(),
            'lang' => $user->getLocale(),
        ]);

        return $this->addToEmailQueue($email, EmailType::VerificationRequest);
    }

    public function prepareWelcome(User $user): bool
    {
        $email = new TemplatedEmail();
        $email->from($this->config->getMailerAddress());
        $email->to((string) $user->getEmail());
        $email->locale($user->getLocale());
        $email->context([
            'url' => $this->config->getUrl(),
            'host' => $this->config->getHost(),
            'lang' => $user->getLocale(),
        ]);

        return $this->addToEmailQueue($email, EmailType::Welcome);
    }

    public function prepareResetPassword(User $user): bool
    {
        $email = new TemplatedEmail();
        $email->from($this->config->getMailerAddress());
        $email->to((string) $user->getEmail());
        $email->locale($user->getLocale());
        $email->context([
            'host' => $this->config->getHost(),
            'token' => $user->getRegcode(),
            'lang' => $user->getLocale(),
            'username' => $user->getName(),
        ]);

        return $this->addToEmailQueue($email, EmailType::PasswordResetRequest);
    }

    public function prepareAggregatedRsvpNotification(User $recipient, array $attendees, Event $event): bool
    {
        $language = $recipient->getLocale();
        $attendeeNames = implode(', ', array_map(fn (User $user) => $user->getName(), $attendees));

        $email = new TemplatedEmail();
        $email->from($this->config->getMailerAddress());
        $email->to((string) $recipient->getEmail());
        $email->locale($language);
        $email->context([
            'username' => $recipient->getName(),
            'attendeeNames' => $attendeeNames,
            'eventLocation' => $event->getLocation()?->getName() ?? '',
            'eventDate' => $event->getStart()->format('Y-m-d'),
            'eventId' => $event->getId(),
            'eventTitle' => $event->getTitle($language),
            'host' => $this->config->getHost(),
            'lang' => $language,
        ]);

        return $this->addToEmailQueue($email, EmailType::NotificationRsvpAggregated);
    }

    public function prepareMessageNotification(User $sender, User $recipient): bool
    {
        $language = $recipient->getLocale();

        $email = new TemplatedEmail();
        $email->from($this->config->getMailerAddress());
        $email->to((string) $recipient->getEmail());
        $email->locale($language);
        $email->context([
            'username' => $recipient->getName(),
            'sender' => $sender->getName(),
            'senderId' => $sender->getId(),
            'host' => $this->config->getHost(),
            'lang' => $language,
        ]);

        return $this->addToEmailQueue($email, EmailType::NotificationMessage);
    }

    public function prepareEventCanceledNotification(User $recipient, Event $event): bool
    {
        $language = $recipient->getLocale();

        $email = new TemplatedEmail();
        $email->from($this->config->getMailerAddress());
        $email->to((string) $recipient->getEmail());
        $email->locale($language);
        $email->context([
            'username' => $recipient->getName(),
            'eventLocation' => $event->getLocation()?->getName() ?? '',
            'eventDate' => $event->getStart()->format('Y-m-d'),
            'eventId' => $event->getId(),
            'eventTitle' => $event->getTitle($language),
            'host' => $this->config->getHost(),
            'lang' => $language,
        ]);

        return $this->addToEmailQueue($email, EmailType::NotificationEventCanceled);
    }

    public function getMockEmailList(): array
    {
        return [
            EmailType::NotificationMessage->value => [
                'subject' => 'You received a message from %senderName%',
                'context' => [
                    'username' => 'John Doe',
                    'sender' => 'john.doe@example.org',
                    'senderId' => 1,
                    'host' => 'https://localhost/en',
                    'lang' => 'en',
                ],
            ],
            EmailType::NotificationRsvpAggregated->value => [
                'subject' => 'People you follow plan to attend an event',
                'context' => [
                    'username' => 'John Doe',
                    'attendeeNames' => 'Denis Matrens, Jane Smith',
                    'eventLocation' => 'NightBar 64',
                    'eventDate' => '2025-01-01',
                    'eventId' => 1,
                    'eventTitle' => 'Go tournament afterparty',
                    'host' => 'https://localhost/en',
                    'lang' => 'en',
                ],
            ],
            EmailType::Welcome->value => [
                'subject' => 'Welcome!',
                'context' => [
                    'host' => 'https://localhost/en',
                    'url' => 'https://localhost/en',
                    'lang' => 'en',
                ],
            ],
            EmailType::VerificationRequest->value => [
                'subject' => 'Please Confirm your Email',
                'context' => [
                    'host' => 'https://localhost/en',
                    'token' => '1234567890',
                    'username' => 'John Doe',
                    'url' => 'https://localhost/en',
                    'lang' => 'en',
                ],
            ],
            EmailType::PasswordResetRequest->value => [
                'subject' => 'Password reset request',
                'context' => [
                    'host' => 'https://localhost/en',
                    'token' => '1234567890',
                    'lang' => 'en',
                    'username' => 'John Doe',
                ],
            ],
            EmailType::NotificationEventCanceled->value => [
                'subject' => 'Event canceled: Go tournament afterparty',
                'context' => [
                    'username' => 'John Doe',
                    'eventLocation' => 'NightBar 64',
                    'eventDate' => '2025-01-01',
                    'eventId' => 1,
                    'eventTitle' => 'Go tournament afterparty',
                    'host' => 'https://localhost/en',
                    'lang' => 'en',
                ],
            ],
        ];
    }

    public function sendQueue(): void
    {
        $mails = $this->mailRepo->findBy(['status' => 'pending'], ['id' => 'ASC'], 1000);
        foreach ($mails as $mail) {
            try {
                $this->mailer->send($this->queueToTemplate($mail));
                $mail->setSendAt(new DateTime());
                $mail->setStatus('sent');
            } catch (TransportExceptionInterface $e) {
                $mail->setStatus('failed');
                $mail->setErrorMessage($e->getMessage());
            }
            $this->em->persist($mail);
        }
        $this->em->flush();
    }

    private function addToEmailQueue(TemplatedEmail $email, EmailType $identifier): bool
    {
        $dbTemplate = $this->templateService->getTemplate($identifier);
        if (!$dbTemplate instanceof EmailTemplate) {
            throw new RuntimeException(sprintf('Email template "%s" not found in database. Run app:email-templates:seed command.', $identifier->value));
        }

        $context = $email->getContext();

        $emailQueue = new EmailQueue();
        $emailQueue->setSender($email->getFrom()[0]->toString());
        $emailQueue->setRecipient($email->getTo()[0]->toString());
        $emailQueue->setLang($email->getLocale() ?? 'en');
        $emailQueue->setContext($context);
        $emailQueue->setCreatedAt(new DateTimeImmutable());
        $emailQueue->setSendAt(null);
        $emailQueue->setSubject($this->templateService->renderContent($dbTemplate->getSubject(), $context));
        $emailQueue->setRenderedBody($this->templateService->renderContent($dbTemplate->getBody(), $context));

        $this->em->persist($emailQueue);
        $this->em->flush();

        return true;
    }

    private function queueToTemplate(EmailQueue $mail): TemplatedEmail
    {
        $template = new TemplatedEmail();
        $template->addFrom($mail->getSender());
        $template->addTo($mail->getRecipient());
        $template->subject($mail->getSubject());
        $template->locale($mail->getLang());
        $template->html($mail->getRenderedBody());

        return $template;
    }
}
