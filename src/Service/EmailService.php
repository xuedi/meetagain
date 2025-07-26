<?php declare(strict_types=1);

namespace App\Service;

use App\Entity\EmailQueue;
use App\Entity\Event;
use App\Entity\User;
use App\Repository\EmailQueueRepository;
use DateTime;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\BodyRenderer;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Part\AbstractPart;
use Twig\Environment;

readonly class EmailService
{
    public function __construct(
        private MailerInterface $mailer,
        private ConfigService $config,
        private EmailQueueRepository $mailRepo,
        private EntityManagerInterface $em
    ) {
    }

    public function sendWelcome(User $user): bool
    {
        $email = new TemplatedEmail();
        $email->from($this->config->getMailerAddress());
        $email->to((string)$user->getEmail());
        $email->subject('Welcome!');
        $email->htmlTemplate('_emails/welcome.html.twig');

        return $this->sendEmail($email);
    }

    public function sendConformationRequest(User $user, Request $request): bool
    {
        $email = new TemplatedEmail();
        $email->from($this->config->getMailerAddress());
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
        $email->from($this->config->getMailerAddress());
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
        $email->from($this->config->getMailerAddress());
        $email->to((string)$userRecipient->getEmail());
        $email->subject('A member you follow plans to attend an event');
        $email->htmlTemplate('_emails/notification_rsvp.html.twig');
        $email->locale($language);
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

        return $this->addToEmailQueue($email); // TODO: implement for all message
    }

    public function sendMessageNotification(User $sender, User $recipient): bool
    {
        $email = new TemplatedEmail();
        $email->from($this->config->getMailerAddress());
        $email->to((string)$recipient->getEmail());
        $email->subject('You received a message from ' . $sender->getName());
        $email->htmlTemplate('_emails/notification_message.html.twig');
        $email->context([
            'username' => $recipient->getName(),
            'sender' => $sender->getName(),
            'senderId' => $sender->getId(),
            'host' => $this->config->getHost(),
            'lang' => $recipient->getLocale(),
        ]);

        return $this->sendEmail($email);
    }

    public function sendQueue(): void
    {
        $mails = $this->mailRepo->findBy(['sendAt' => null], ['id' => 'ASC'], 1000);
        foreach ($mails as $mail) {
            $this->sendEmail($this->queueToTemplate($mail));
            $mail->setSendAt(new DateTime());

            $this->em->persist($mail);
        }
        $this->em->flush();
    }

    private function sendEmail(TemplatedEmail $email): bool
    {
        try {
            $this->mailer->send($email);

            return true;
        } catch (TransportExceptionInterface) {
            // TODO: add logging
            return false;
        }
    }

    private function addToEmailQueue(TemplatedEmail $email): bool
    {
        $emailQueue = new EmailQueue();
        $emailQueue->setSender($email->getFrom()[0]->toString());
        $emailQueue->setRecipient($email->getTo()[0]->toString());
        $emailQueue->setSubject($email->getSubject());
        $emailQueue->setTemplate($email->getHtmlTemplate());
        $emailQueue->setLang($email->getLocale());
        $emailQueue->setContext($email->getContext());
        $emailQueue->setCreatedAt(new DateTimeImmutable());
        $emailQueue->setSendAt(null);

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
        $template->htmlTemplate($mail->getTemplate());
        $template->context($mail->getContext());
        $template->locale($mail->getLang());

        return $template;
    }
}
