<?php declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

readonly class EmailService
{
    public function __construct(private MailerInterface $mailer)
    {
    }

    public function sendWelcome(User $user): bool
    {
        $email = new TemplatedEmail();
        $email->from(new Address('registration@dragon-descendants.de', 'Dragon Descendants Meetup'));
        $email->to((string)$user->getEmail());
        $email->subject('Welcome!');
        $email->htmlTemplate('_emails/welcome.html.twig');

        return $this->sendEmail($email);
    }

    public function sendEmailConformationRequest(User $user, Request $request): bool
    {
        $email = new TemplatedEmail();
        $email->from(new Address('registration@dragon-descendants.de', 'Dragon Descendants Meetup'));
        $email->to((string)$user->getEmail());
        $email->subject('Please Confirm your Email');
        $email->htmlTemplate('_emails/verification_request.html.twig');
        $email->context([
            'host' => sprintf('%s://%s', $request->getScheme(), $request->getHost()),
            'token' => $user->getRegcode(),
            'lang' => $request->getLocale(),
            'username' => $user->getName(),
        ]);

        return $this->sendEmail($email);
    }

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
}
