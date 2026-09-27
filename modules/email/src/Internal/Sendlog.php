<?php declare(strict_types=1);

namespace Module\Email\Internal;

use DateTimeImmutable;
use Module\Email\Contract\QueueStats;
use Module\Email\Contract\QueueStatus;
use Module\Email\Contract\SendlogInterface;
use Module\Email\Contract\SentEmail;
use Module\Email\Internal\Entity\EmailQueue;
use Module\Email\Internal\Repository\EmailQueueRepository;

final readonly class Sendlog implements SendlogInterface
{
    private const int STALE_MINUTES = 60;

    public function __construct(
        private EmailQueueRepository $repo,
    ) {}

    public function find(int $id): ?SentEmail
    {
        $row = $this->repo->find($id);

        return $row instanceof EmailQueue ? $this->toSentEmail($row) : null;
    }

    public function list(int $limit = 100, ?string $recipient = null, ?string $template = null, ?QueueStatus $status = null): array
    {
        $rows = $this->repo->findFiltered($limit, template: $template, recipient: $recipient, statuses: $status instanceof QueueStatus ? [$status] : null);

        return array_values(array_map($this->toSentEmail(...), $rows));
    }

    public function countAll(): int
    {
        return $this->repo->countAll();
    }

    public function countBetween(DateTimeImmutable $from, DateTimeImmutable $to): int
    {
        return $this->repo->countCreatedBetween($from, $to);
    }

    public function stats(): QueueStats
    {
        return new QueueStats(pending: $this->repo->getPendingCount(), stale: $this->repo->getStaleCount(self::STALE_MINUTES));
    }

    private function toSentEmail(EmailQueue $row): SentEmail
    {
        $context = $row->getContext();

        return new SentEmail(
            id: (int) $row->getId(),
            template: $row->getTemplate(),
            sender: (string) $row->getSender(),
            recipient: (string) $row->getRecipient(),
            subject: (string) $row->getSubject(),
            lang: (string) $row->getLang(),
            status: $row->getStatus(),
            context: array_filter($context, static fn(string|int $key): bool => !str_starts_with((string) $key, '_'), ARRAY_FILTER_USE_KEY),
            layout: (array) ($context[LayoutRenderer::CONTEXT_KEY] ?? []),
            renderedBody: $row->getRenderedBody(),
            createdAt: $row->getCreatedAt(),
            maxSendBy: $row->getMaxSendBy(),
            dispatchedAt: $row->getProviderDispatchedAt(),
            providerStatus: $row->getProviderStatus(),
            errorMessage: $row->getErrorMessage(),
        );
    }
}
