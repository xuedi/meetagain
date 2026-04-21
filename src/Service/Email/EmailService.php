<?php declare(strict_types=1);

namespace App\Service\Email;

use App\CronTaskInterface;
use App\EmailContextEnricherInterface;
use App\Emails\EmailInterface;
use App\Emails\EmailQueueInterface;
use App\Enum\CronTaskStatus;
use App\ValueObject\CronTaskResult;
use App\Entity\EmailQueue;
use App\Enum\EmailQueueStatus;
use App\Enum\EmailType;
use App\Repository\EmailQueueRepository;
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

    public function enqueue(
        EmailInterface $source,
        TemplatedEmail $email,
        EmailType $type,
        array $context,
        bool $flush = true,
    ): bool {
        $locale = $email->getLocale() ?? 'en';
        $templateContent = $this->templateService->getTemplateContent($type, $locale);

        $twigContext = array_merge(['greeting' => ''], $email->getContext());

        foreach ($this->enrichers as $enricher) {
            $twigContext = $enricher->enrich($twigContext, $locale);
        }

        $now = new DateTimeImmutable();

        $emailQueue = new EmailQueue();
        $emailQueue->setSender($email->getFrom()[0]->toString());
        $emailQueue->setRecipient($email->getTo()[0]->toString());
        $emailQueue->setLang($locale);
        $emailQueue->setContext($twigContext);
        $emailQueue->setCreatedAt($now);
        $emailQueue->setMaxSendBy($source->getMaxSendBy($context, $now));
        $emailQueue->setTemplate($type);
        $emailQueue->setSubject($this->templateService->renderContent($templateContent['subject'], $twigContext));
        $emailQueue->setRenderedBody($this->templateService->renderContent($templateContent['body'], $twigContext));

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
            $status = (str_contains($result, '(Failed:') || str_contains($result, '(Late:'))
                ? CronTaskStatus::warning
                : CronTaskStatus::ok;

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
        $late = 0;
        $now = new DateTimeImmutable();
        $mails = $this->mailRepo->findBy(['status' => EmailQueueStatus::Pending], ['id' => 'ASC'], 1000);
        foreach ($mails as $mail) {
            $cutoff = $mail->getMaxSendBy();
            if ($cutoff !== null && $now > $cutoff) {
                $mail->setStatus(EmailQueueStatus::Late);
                $mail->setErrorMessage(sprintf(
                    'Dispatch cutoff passed: max_send_by=%s, now=%s',
                    $cutoff->format('c'),
                    $now->format('c'),
                ));
                $this->logger->error('Email dispatch skipped: past max_send_by cutoff', [
                    'email_queue_id' => $mail->getId(),
                    'template' => $mail->getTemplate()?->value,
                    'recipient' => $mail->getRecipient(),
                    'created_at' => $mail->getCreatedAt()?->format('c'),
                    'max_send_by' => $cutoff->format('c'),
                    'now' => $now->format('c'),
                ]);
                $this->em->persist($mail);
                $late++;
                continue;
            }

            try {
                $sentMessage = $this->transport->send($this->queueToTemplate($mail));
                $mail->setProviderDispatchedAt(new DateTimeImmutable());
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

        if ($failed > 0 || $late > 0) {
            $this->logger->warning('Email queue processed with issues', [
                'sent' => $send, 'failed' => $failed, 'late' => $late,
            ]);

            $parts = [];
            if ($failed > 0) {
                $parts[] = sprintf('Failed: %d', $failed);
            }
            if ($late > 0) {
                $parts[] = sprintf('Late: %d', $late);
            }

            return sprintf('%d (%s)', $send, implode(', ', $parts));
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
