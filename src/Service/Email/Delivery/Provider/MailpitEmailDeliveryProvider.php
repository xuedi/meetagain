<?php declare(strict_types=1);

namespace App\Service\Email\Delivery\Provider;

use App\Service\Email\Delivery\EmailDeliveryProviderInterface;
use App\Service\Email\Delivery\Log;
use App\Service\Email\Delivery\LogCollection;
use App\Service\Email\Delivery\LogFilter;
use DateTimeImmutable;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

#[AsTaggedItem(priority: 100)]
final readonly class MailpitEmailDeliveryProvider implements EmailDeliveryProviderInterface
{
    private const string STATUS = 'delivered';
    private const string PROVIDER = 'mailpit';
    private const string DEV_HOST = 'mailpit';
    private const int API_PORT = 8025;

    private string $baseUrl;

    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        #[Autowire(env: 'MAILER_DSN')]
        string $mailerDsn,
    ) {
        $parsed = parse_url($mailerDsn);
        $isDevCatcher = ($parsed['scheme'] ?? '') === 'smtp' && ($parsed['host'] ?? '') === self::DEV_HOST;
        $this->baseUrl = $isDevCatcher ? sprintf('http://%s:%d', self::DEV_HOST, self::API_PORT) : '';
    }

    public function isAvailable(): bool
    {
        return $this->baseUrl !== '';
    }

    public function getLogs(LogFilter $filter): LogCollection
    {
        if (!$this->isAvailable()) {
            return new LogCollection([], 0, $filter->offset, $filter->size);
        }

        if ($filter->messageId !== null) {
            $log = $this->getLogByMessageId($filter->messageId);

            return new LogCollection($log === null ? [] : [$log], $log === null ? 0 : 1, $filter->offset, $filter->size);
        }

        try {
            $response = $this->httpClient->request('GET', $this->baseUrl . '/api/v1/search', [
                'query' => [
                    'query' => $this->buildQuery($filter),
                    'start' => $filter->offset,
                    'limit' => $filter->size,
                ],
            ]);

            $data = $response->toArray();
            $items = array_map($this->mapLog(...), $data['messages'] ?? []);

            return new LogCollection($items, $data['messages_count'] ?? count($items), $filter->offset, $filter->size);
        } catch (Throwable $e) {
            $this->logger->error('Mailpit API request failed', [
                'message' => $e->getMessage(),
                'filter' => (array) $filter,
            ]);

            return new LogCollection([], 0, $filter->offset, $filter->size);
        }
    }

    public function getLogByMessageId(string $messageId): ?Log
    {
        if (!$this->isAvailable() || $messageId === '') {
            return null;
        }

        try {
            $response = $this->httpClient->request('GET', sprintf('%s/api/v1/message/%s', $this->baseUrl, rawurlencode($messageId)));
            if ($response->getStatusCode() !== 200) {
                return null;
            }

            return $this->mapLog($response->toArray());
        } catch (Throwable $e) {
            $this->logger->error('Mailpit API request failed', [
                'message' => $e->getMessage(),
                'provider_message_id' => $messageId,
            ]);

            return null;
        }
    }

    private function buildQuery(LogFilter $filter): string
    {
        $parts = [];
        if ($filter->recipientEmail !== null) {
            $parts[] = 'to:' . $filter->recipientEmail;
        }
        if ($filter->since !== null) {
            $parts[] = 'after:' . $filter->since->format('Y-m-d');
        }
        if ($filter->until !== null) {
            $parts[] = 'before:' . $filter->until->format('Y-m-d');
        }

        return implode(' ', $parts);
    }

    /** @param array<string, mixed> $data */
    private function mapLog(array $data): Log
    {
        $received = $data['Date'] ?? $data['Created'] ?? null;
        $timestamp = is_string($received) ? new DateTimeImmutable($received) : new DateTimeImmutable();

        return new Log(
            messageId: (string) ($data['ID'] ?? ''),
            status: self::STATUS,
            recipientEmail: (string) ($data['To'][0]['Address'] ?? ''),
            createdAt: $timestamp,
            updatedAt: $timestamp,
            bounceType: null,
            mailboxProvider: self::PROVIDER,
            rawData: $data,
        );
    }
}
