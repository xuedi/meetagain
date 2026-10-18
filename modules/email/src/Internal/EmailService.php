<?php declare(strict_types=1);

namespace Module\Email\Internal;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use LogicException;
use Module\Email\Contract\Attachment;
use Module\Email\Contract\ContextEnricherInterface;
use Module\Email\Contract\EmailInterface;
use Module\Email\Contract\PushDispatcherInterface;
use Module\Email\Contract\QueueStatus;
use Module\Email\Contract\SendingIdentity;
use Module\Email\Contract\SendingIdentityProviderInterface;
use Module\Email\Internal\Entity\EmailQueue;
use Module\Email\Internal\Message\Drain;
use Module\Email\Internal\Repository\EmailQueueRepository;
use Module\Task\Contract\Cadence;
use Module\Task\Contract\Result;
use Module\Task\Contract\Status;
use Module\Task\Contract\TaskInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Mime\MimeTypes;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\File;
use Throwable;

readonly class EmailService implements TaskInterface, EmailQueueInterface
{
    private const int CHUNK_SIZE = 200;
    private const int SEND_BUDGET_SECONDS = 50;
    private const int CLAIM_LEASE_SECONDS = 600;

    /**
     * @param iterable<ContextEnricherInterface> $enrichers
     * @param iterable<SendingIdentityProviderInterface> $identityProviders
     */
    public function __construct(
        #[Autowire(service: 'mailer.transports')]
        private TransportInterface $transport,
        private EmailQueueRepository $mailRepo,
        private EntityManagerInterface $em,
        private EmailTemplateService $templateService,
        private LayoutRenderer $layoutRenderer,
        private LoggerInterface $logger,
        #[AutowireIterator(ContextEnricherInterface::class)]
        private iterable $enrichers,
        #[AutowireIterator(SendingIdentityProviderInterface::class)]
        private iterable $identityProviders,
        #[AutowireIterator(PushDispatcherInterface::class)]
        private iterable $pushDispatchers,
        private ClockInterface $clock,
        private MessageBusInterface $bus,
        private FilesystemOperator $privateStorage,
    ) {}

    public function enqueue(
        EmailInterface $source,
        TemplatedEmail $email,
        array $context,
        bool $flush = true,
        ?object $origin = null,
        bool $dispatchPush = true,
    ): bool {
        $identifier = $source->getIdentifier();
        $locale = $email->getLocale() ?? 'en';
        $identity = $this->resolveIdentity($origin ?? $source->getOrigin($context), $locale);

        $twigContext = array_merge(['greeting' => $identity->greeting], $email->getContext());

        foreach ($this->enrichers as $enricher) {
            $twigContext = $enricher->enrich($twigContext, $locale);
        }

        $twigContext['host'] = $identity->siteUrl;
        $twigContext['url'] = $this->hostPortion($identity->siteUrl);
        $twigContext[LayoutRenderer::CONTEXT_KEY] = $this->layoutRenderer->snapshot($identity);

        $now = new DateTimeImmutable();

        $emailQueue = new EmailQueue();
        $emailQueue->setSender($email->getFrom()[0]->toString());
        $emailQueue->setRecipient($email->getTo()[0]->toString());
        $emailQueue->setLang($locale);
        $emailQueue->setContext($twigContext);
        $emailQueue->setCreatedAt($now);
        $emailQueue->setMaxSendBy($source->getMaxSendBy($context, $now));
        $emailQueue->setTemplate($identifier);
        $emailQueue->setAttachments($source->getAttachments($context));
        $rendered = $this->templateService->render($identifier, $locale, $twigContext);
        $emailQueue->setSubject($rendered['subject']);
        $emailQueue->setRenderedBody($rendered['body']);

        $this->em->persist($emailQueue);
        if ($dispatchPush) {
            $this->dispatchPush($identifier, $emailQueue->getRecipient(), $emailQueue->getMaxSendBy());
        }
        if ($flush) {
            $this->em->flush();
            $this->wake();
        }

        return true;
    }

    public function wake(): void
    {
        $this->bus->dispatch(new Drain());
    }

    #[AsMessageHandler]
    public function drain(Drain $message): void
    {
        if ($this->drainQueue()['more']) {
            $this->wake();
        }
    }

    public function dispatchPush(string $identifier, string $recipient, ?DateTimeImmutable $deadline): void
    {
        foreach ($this->pushDispatchers as $dispatcher) {
            try {
                $dispatcher->dispatch($identifier, $recipient, $deadline);
            } catch (Throwable $e) {
                $this->logger->error('Push dispatch failed', ['identifier' => $identifier, 'exception' => $e]);
            }
        }
    }

    public function getIdentifier(): string
    {
        return 'email-queue';
    }

    public function getCadence(): Cadence
    {
        return new Cadence('*/5 * * * *', offloaded: true);
    }

    public function getDescription(): string
    {
        return 'Sends queued emails. Every five minutes it claims pending mails in batches and hands them to the mail transport until the queue is empty or the time budget is spent, marking each mail sent or failed. A mail past its send-by deadline, such as a reminder for an event that already started, is marked late instead of being sent. Failed or late mails turn the run into a warning.';
    }

    public function run(): Result
    {
        try {
            $result = $this->sendQueue();
            $status = str_contains($result, '(Failed:') || str_contains($result, '(Late:') ? Status::warning : Status::ok;

            return new Result($status, $result);
        } catch (Throwable $e) {
            return new Result(Status::exception, $e->getMessage());
        }
    }

    public function sendQueue(): string
    {
        return $this->drainQueue()['message'];
    }

    /**
     * @return array{message: string, more: bool}
     */
    private function drainQueue(): array
    {
        $send = 0;
        $failed = 0;
        $late = 0;
        $startedAt = $this->clock->now();
        $stopAt = $startedAt->modify(sprintf('+%d seconds', self::SEND_BUDGET_SECONDS));
        do {
            $claimedAt = $this->clock->now();
            $mails = $this->mailRepo->claimPending(self::CHUNK_SIZE, $claimedAt, $claimedAt->modify(sprintf('-%d seconds', self::CLAIM_LEASE_SECONDS)));
            foreach ($mails as $mail) {
                $now = $this->clock->now();
                $cutoff = $mail->getMaxSendBy();
                if ($cutoff !== null && $now > $cutoff) {
                    $mail->setStatus(QueueStatus::Late);
                    $mail->setErrorMessage(sprintf('Dispatch cutoff passed: max_send_by=%s, now=%s', $cutoff->format('c'), $now->format('c')));
                    $this->logger->error('Email dispatch skipped: past max_send_by cutoff', [
                        'email_queue_id' => $mail->getId(),
                        'template' => $mail->getTemplate(),
                        'recipient' => $mail->getRecipient(),
                        'created_at' => $mail->getCreatedAt()?->format('c'),
                        'max_send_by' => $cutoff->format('c'),
                        'now' => $now->format('c'),
                    ]);
                    $mail->releaseClaim();
                    $this->em->flush();
                    $late++;
                    continue;
                }

                try {
                    $sentMessage = $this->transport->send($this->queueToTemplate($mail));
                    $mail->setProviderDispatchedAt($this->clock->now());
                    $mail->setStatus(QueueStatus::Sent);
                    if ($sentMessage->getMessageId() !== '') {
                        $mail->setProviderMessageId($sentMessage->getMessageId());
                    }
                    $send++;
                } catch (TransportExceptionInterface $e) {
                    $mail->setStatus(QueueStatus::Failed);
                    $mail->setErrorMessage($e->getMessage());
                    $failed++;
                }
                $mail->releaseClaim();
                $this->em->flush();
            }
            foreach ($mails as $mail) {
                $this->em->detach($mail);
            }
        } while (count($mails) === self::CHUNK_SIZE && $this->clock->now() < $stopAt);

        $more = count($mails) === self::CHUNK_SIZE;
        $pending = $more ? $this->mailRepo->count(['status' => QueueStatus::Pending]) : 0;

        if ($failed > 0 || $late > 0) {
            $this->logger->warning('Email queue processed with issues', [
                'sent' => $send,
                'failed' => $failed,
                'late' => $late,
                'pending' => $pending,
            ]);

            $parts = [];
            if ($failed > 0) {
                $parts[] = sprintf('Failed: %d', $failed);
            }
            if ($late > 0) {
                $parts[] = sprintf('Late: %d', $late);
            }
            if ($pending > 0) {
                $parts[] = sprintf('Pending: %d', $pending);
            }

            return ['message' => sprintf('%d (%s)', $send, implode(', ', $parts)), 'more' => $more];
        }

        $this->logger->info('Email queue processed', ['sent' => $send, 'pending' => $pending]);
        if ($pending > 0) {
            return ['message' => sprintf('%d (Pending: %d)', $send, $pending), 'more' => $more];
        }

        return ['message' => sprintf('%d', $send), 'more' => $more];
    }

    private function resolveIdentity(?object $origin, string $locale): SendingIdentity
    {
        foreach ($this->identityProviders as $provider) {
            $identity = $provider->resolve($origin, $locale);
            if ($identity !== null) {
                return $identity;
            }
        }

        throw new LogicException('No sending identity provider answered.');
    }

    private function hostPortion(string $siteUrl): string
    {
        $host = parse_url($siteUrl, PHP_URL_HOST);

        return is_string($host) ? $host : $siteUrl;
    }

    private function queueToTemplate(EmailQueue $mail): TemplatedEmail
    {
        $template = new TemplatedEmail();
        $template->addFrom($mail->getSender());
        $template->addTo($mail->getRecipient());
        $template->subject($mail->getSubject());
        $template->locale($mail->getLang());

        $template->html($this->layoutRenderer->wrap($mail));

        foreach ($mail->getAttachments() as $attachment) {
            $part = $this->attachmentPart($attachment);
            if ($part === null) {
                $this->logger->warning('Email attachment is missing and was skipped', [
                    'email_queue_id' => $mail->getId(),
                    'path' => $attachment->path,
                ]);
                continue;
            }

            $template->addPart($part);
        }

        return $template;
    }

    private function attachmentPart(Attachment $attachment): ?DataPart
    {
        if (!$attachment->stored) {
            return is_readable($attachment->path) ? new DataPart(new File($attachment->path), $attachment->filename) : null;
        }

        try {
            $contentType = MimeTypes::getDefault()->getMimeTypes(pathinfo($attachment->filename, PATHINFO_EXTENSION))[0] ?? null;

            return new DataPart($this->privateStorage->read($attachment->path), $attachment->filename, $contentType);
        } catch (FilesystemException) {
            return null;
        }
    }
}
