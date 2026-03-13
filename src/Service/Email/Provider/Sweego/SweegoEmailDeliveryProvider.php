<?php declare(strict_types=1);

namespace App\Service\Email\Provider\Sweego;

use App\Service\Email\Provider\EmailDeliveryLog;
use App\Service\Email\Provider\EmailDeliveryLogCollection;
use App\Service\Email\Provider\EmailDeliveryLogFilter;
use App\Service\Email\Provider\EmailDeliveryProviderInterface;
use DateTimeImmutable;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

final readonly class SweegoEmailDeliveryProvider implements EmailDeliveryProviderInterface
{
    private const BASE_URL = 'https://api.sweego.io';

    private string $apiKey;

    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        string $mailerDsn,
    ) {
        $parsed = parse_url($mailerDsn);
        $this->apiKey = urldecode($parsed['user'] ?? '');
    }

    public function isAvailable(): bool
    {
        return $this->apiKey !== '';
    }

    public function getLogs(EmailDeliveryLogFilter $filter): EmailDeliveryLogCollection
    {
        if (!$this->isAvailable()) {
            return new EmailDeliveryLogCollection([], 0, $filter->offset, $filter->size);
        }

        try {
            $body = ['offset' => $filter->offset, 'size' => $filter->size];

            if ($filter->messageId !== null) {
                $body['transaction_id'] = $filter->messageId;
            }
            if ($filter->recipientEmail !== null) {
                $body['email_to'] = $filter->recipientEmail;
            }
            if ($filter->statuses !== null) {
                $body['status'] = $filter->statuses;
            }
            if ($filter->since !== null) {
                $body['date_from'] = $filter->since->format('Y-m-d');
            }
            if ($filter->until !== null) {
                $body['date_to'] = $filter->until->format('Y-m-d');
            }

            $response = $this->httpClient->request('POST', self::BASE_URL . '/logs/', [
                'headers' => $this->makeHeaders(),
                'json' => $body,
            ]);

            $data = $response->toArray();
            $items = array_map($this->mapLog(...), $data['logs'] ?? []);

            return new EmailDeliveryLogCollection(
                $items,
                $data['total'] ?? count($items),
                $filter->offset,
                $filter->size,
            );
        } catch (Throwable $e) {
            $this->logger->error('Sweego API request failed', [
                'message' => $e->getMessage(),
                'filter' => (array) $filter,
            ]);

            return new EmailDeliveryLogCollection([], 0, $filter->offset, $filter->size);
        }
    }

    public function getLogByMessageId(string $messageId): ?EmailDeliveryLog
    {
        $collection = $this->getLogs(new EmailDeliveryLogFilter(messageId: $messageId, size: 1));

        return $collection->isEmpty() ? null : $collection->items[0];
    }

    private function mapLog(array $data): EmailDeliveryLog
    {
        return new EmailDeliveryLog(
            messageId: $data['transaction_id'] ?? $data['swg_uid'] ?? '',
            status: $data['status'] ?? 'unknown',
            recipientEmail: $data['email_to'] ?? '',
            createdAt: isset($data['email_creation'])
                ? new DateTimeImmutable($data['email_creation'])
                : new DateTimeImmutable(),
            updatedAt: isset($data['email_last_update'])
                ? new DateTimeImmutable($data['email_last_update'])
                : new DateTimeImmutable(),
            bounceType: $data['bounce_type'] ?? null,
            mailboxProvider: $data['msp'] ?? null,
            rawData: $data,
        );
    }

    private function makeHeaders(): array
    {
        return ['Api-Key' => $this->apiKey];
    }
}
