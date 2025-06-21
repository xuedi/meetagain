<?php declare(strict_types=1);

namespace App\Service;

use App\Entity\Event;
use App\Entity\User;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

readonly class EmailService
{
    public function __construct(private MailerInterface $mailer, private ConfigService $config)
    {
    }

    public function sendWelcome(User $user): bool
    {
        $email = new TemplatedEmail();
        $email->from($this->getSenderAddress());
        $email->to((string)$user->getEmail());
        $email->subject('Welcome!');
        $email->htmlTemplate('_emails/welcome.html.twig');

        return $this->sendEmail($email);
    }

    public function sendConformationRequest(User $user, Request $request): bool
    {
        $email = new TemplatedEmail();
        $email->from($this->getSenderAddress());
        $email->to((string)$user->getEmail());
        $email->subject('Please Confirm your Email');
        $email->htmlTemplate('_emails/verification_request.html.twig');
        $email->context([
            'host' => $this->config->getHost(),
            'token' => $user->getRegcode(),
            'lang' => $request->getLocale(),
            'username' => $user->getName(),
        ]);

        return $this->sendEmail($email);
    }

    public function sendResetPasswordRequest(User $user, Request $request): bool
    {
        $email = new TemplatedEmail();
        $email->from($this->getSenderAddress());
        $email->to((string)$user->getEmail());
        $email->subject('Password reset request');
        $email->htmlTemplate('_emails/password_reset_request.html.twig');
        $email->context([
            'host' => $this->config->getHost(),
            'token' => $user->getRegcode(),
            'lang' => $request->getLocale(),
            'username' => $user->getName(),
        ]);

        return $this->sendEmail($email);
    }

    public function sendRsvpNotification(User $userRsvp, User $userRecipient, Event $event): bool
    {
        $language = $userRecipient->getLocale();

        $email = new TemplatedEmail();
        $email->from($this->getSenderAddress());
        $email->to((string)$userRecipient->getEmail());
        $email->subject('A member you follow plans to attend an event');
        $email->htmlTemplate('_emails/notification_rsvp.html.twig');
        $email->context([
            'username' => $userRecipient->getName(),
            'followedUserName' => $userRsvp->getName(),
            'eventLocation' => $event->getLocation()->getName(),
            'eventDate' => $event->getStart()->format('Y-m-d'),
            'eventId' => $event->getId(),
            'eventTitle' => $event->getTitle($language),
            'host' => $this->config->getHost(),
            'lang' => $language,
        ]);

        return $this->sendEmail($email);
    }

    // TODO: buffer in message queue first
    private function sendEmail(TemplatedEmail $email): bool
    {
        try {
            $this->mailer->send($email);

            return true;
        } catch (TransportExceptionInterface $e) {
            // TODO: add logging
            return false;
        }
    }

    private function getSenderAddress(): Address
    {
        return new Address('service@dragon-descendants.de', 'Dragon Descendants Meetup');
    }
}
