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
        $email->subject('Please Confirm your Email');
        $email->htmlTemplate('_emails/verification_request.html.twig');
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
        $email->subject('Welcome!');
        $email->htmlTemplate('_emails/welcome.html.twig');
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
        $email->subject('Password reset request');
        $email->htmlTemplate('_emails/password_reset_request.html.twig');
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
        $count = count($attendees);

        $email = new TemplatedEmail();
        $email->from($this->config->getMailerAddress());
        $email->to((string) $recipient->getEmail());

        $subject = $count > 1
            ? 'People you follow plan to attend an event'
            : sprintf('%s plans to attend an event', $attendees[0]->getName());

        $attendeesData = array_map(
            fn (User $user) => ['name' => $user->getName()],
            $attendees
        );
        $attendeeNames = implode(', ', array_map(fn (User $user) => $user->getName(), $attendees));

        $email->subject($subject);
        $email->htmlTemplate('_emails/notification_rsvp_aggregated.html.twig');
        $email->locale($language);
        $email->context([
            'username' => $recipient->getName(),
            'attendees' => $attendeesData,
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
        $email->subject('You received a message from ' . $sender->getName());
        $email->htmlTemplate('_emails/notification_message.html.twig');
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
        $email->subject('Event canceled: ' . $event->getTitle($language));
        $email->htmlTemplate('_emails/notification_event_canceled.html.twig');
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
            'email_message_notification' => [
                'subject' => 'You received a message from %senderName%',
                'template' => '_emails/notification_message.html.twig',
                'context' => [
                    'username' => 'John Doe',
                    'sender' => 'john.doe@example.org',
                    'senderId' => 1,
                    'host' => 'https://localhost/en',
                    'lang' => 'en',
                ],
            ],
            'email_rsvp_notification_aggregated' => [
                'subject' => 'People you follow plan to attend an event',
                'template' => '_emails/notification_rsvp_aggregated.html.twig',
                'context' => [
                    'username' => 'John Doe',
                    'attendees' => [
                        ['name' => 'Denis Matrens'],
                        ['name' => 'Jane Smith'],
                    ],
                    'attendeeNames' => 'Denis Matrens, Jane Smith',
                    'eventLocation' => 'NightBar 64',
                    'eventDate' => '2025-01-01',
                    'eventId' => 1,
                    'eventTitle' => 'Go tournament afterparty',
                    'host' => 'https://localhost/en',
                    'lang' => 'en',
                ],
            ],
            'email_welcome' => [
                'subject' => 'Welcome!',
                'template' => '_emails/welcome.html.twig',
                'context' => [
                    'host' => 'https://localhost/en',
                    'url' => 'https://localhost/en',
                    'lang' => 'en',
                ],
            ],
            'email_verification_request' => [
                'subject' => 'Please Confirm your Email',
                'template' => '_emails/verification_request.html.twig',
                'context' => [
                    'host' => 'https://localhost/en',
                    'token' => '1234567890',
                    'username' => 'John Doe',
                    'url' => 'https://localhost/en',
                    'lang' => 'en',
                ],
            ],
            'email_password_reset_request' => [
                'subject' => 'Password reset request',
                'template' => '_emails/password_reset_request.html.twig',
                'context' => [
                    'host' => 'https://localhost/en',
                    'token' => '1234567890',
                    'lang' => 'en',
                    'username' => 'John Doe',
                ],
            ],
            'email_event_canceled' => [
                'subject' => 'Event canceled: Go tournament afterparty',
                'template' => '_emails/notification_event_canceled.html.twig',
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
        $mails = $this->mailRepo->findBy(['sendAt' => null], ['id' => 'ASC'], 1000);
        foreach ($mails as $mail) {
            // try {
            $this->mailer->send($this->queueToTemplate($mail));
            $mail->setSendAt(new DateTime());
            $this->em->persist($mail);

            // } catch (TransportExceptionInterface $e) {
            //    continue;
            //    // TODO: add new entity for failed email send messages
            // }
        }
        $this->em->flush();
    }

    private function addToEmailQueue(TemplatedEmail $email, EmailType $identifier): bool
    {
        $emailQueue = new EmailQueue();
        $emailQueue->setSender($email->getFrom()[0]->toString());
        $emailQueue->setRecipient($email->getTo()[0]->toString());
        $emailQueue->setLang($email->getLocale() ?? 'en');
        $emailQueue->setContext($email->getContext());
        $emailQueue->setCreatedAt(new DateTimeImmutable());
        $emailQueue->setSendAt(null);

        $dbTemplate = $this->templateService->getTemplate($identifier);
        if ($dbTemplate instanceof EmailTemplate) {
            $context = $email->getContext();
            $emailQueue->setSubject($this->templateService->renderContent($dbTemplate->getSubject(), $context));
            $emailQueue->setRenderedBody($this->templateService->renderContent($dbTemplate->getBody(), $context));
            $emailQueue->setTemplate(null);
        } else {
            $emailQueue->setSubject($email->getSubject());
            $emailQueue->setTemplate($email->getHtmlTemplate());
        }

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

        if ($mail->getRenderedBody() !== null) {
            $template->html($mail->getRenderedBody());
        } else {
            $template->htmlTemplate($mail->getTemplate());
            $template->context($mail->getContext());
        }

        return $template;
    }
}
