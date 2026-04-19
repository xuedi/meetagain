<?php declare(strict_types=1);

namespace App\Service\Email;

use App\CronTaskInterface;
use App\EmailContextEnricherInterface;
use App\Emails\EmailQueueInterface;
use App\Enum\CronTaskStatus;
use App\ValueObject\CronTaskResult;
use App\Entity\EmailQueue;
use App\Enum\EmailQueueStatus;
use App\Enum\EmailType;
use App\Repository\EmailQueueRepository;
use DateTime;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\Transport\TransportInterface;

readonly class EmailService implements CronTaskInterface, EmailQueueInterface
{
    /**
     * @param iterable<EmailContextEnricherInterface> $enrichers
     */
    public function __construct(
        #[Autowire(service: 'mailer.transports')]
        private TransportInterface $transport,
        private EmailQueueRepository $mailRepo,
        private EntityManagerInterface $em,
        private EmailTemplateService $templateService,
        private LoggerInterface $logger,
        #[AutowireIterator(EmailContextEnricherInterface::class)]
        private iterable $enrichers,
    ) {}

    public function enqueue(TemplatedEmail $email, EmailType $type, bool $flush = true): bool
    {
        $locale = $email->getLocale() ?? 'en';
        $templateContent = $this->templateService->getTemplateContent($type, $locale);

        $context = array_merge(['greeting' => ''], $email->getContext());

        foreach ($this->enrichers as $enricher) {
            $context = $enricher->enrich($context, $locale);
        }

        $emailQueue = new EmailQueue();
        $emailQueue->setSender($email->getFrom()[0]->toString());
        $emailQueue->setRecipient($email->getTo()[0]->toString());
        $emailQueue->setLang($locale);
        $emailQueue->setContext($context);
        $emailQueue->setCreatedAt(new DateTimeImmutable());
        $emailQueue->setSendAt(null);
        $emailQueue->setTemplate($type);
        $emailQueue->setSubject($this->templateService->renderContent($templateContent['subject'], $context));
        $emailQueue->setRenderedBody($this->templateService->renderContent($templateContent['body'], $context));

        $this->em->persist($emailQueue);
        if ($flush) {
            $this->em->flush();
        }

        return true;
    }

    public function getIdentifier(): string
    {
        return 'email-queue';
    }

    public function runCronTask(OutputInterface $output): CronTaskResult
    {
        try {
            $result = $this->sendQueue();
            $output->writeln('EmailService: ' . $result);
            $status = str_contains($result, '(Failed:') ? CronTaskStatus::warning : CronTaskStatus::ok;

            return new CronTaskResult($this->getIdentifier(), $status, $result);
        } catch (\Throwable $e) {
            $output->writeln('EmailService exception: ' . $e->getMessage());

            return new CronTaskResult($this->getIdentifier(), CronTaskStatus::exception, $e->getMessage());
        }
    }

    public function sendQueue(): string
    {
        $send = 0;
        $failed = 0;
        $mails = $this->mailRepo->findBy(['status' => EmailQueueStatus::Pending], ['id' => 'ASC'], 1000);
        foreach ($mails as $mail) {
            try {
                $sentMessage = $this->transport->send($this->queueToTemplate($mail));
                $mail->setSendAt(new DateTime());
                $mail->setStatus(EmailQueueStatus::Sent);
                if ($sentMessage->getMessageId() !== '') {
                    $mail->setProviderMessageId($sentMessage->getMessageId());
                }
                $send++;
            } catch (TransportExceptionInterface $e) {
                $mail->setStatus(EmailQueueStatus::Failed);
                $mail->setErrorMessage($e->getMessage());
                $failed++;
            }
            $this->em->persist($mail);
        }
        $this->em->flush();

        if ($failed > 0) {
            $this->logger->warning('Email queue processed with failures', ['sent' => $send, 'failed' => $failed]);
            return sprintf('%d (Failed: %d)', $send, $failed);
        }

        $this->logger->info('Email queue processed', ['sent' => $send]);
        return sprintf('%d', $send);
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
