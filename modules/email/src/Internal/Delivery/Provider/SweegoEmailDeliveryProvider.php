<?php declare(strict_types=1);

namespace Module\Email\Internal\Delivery\Provider;

use DateTimeImmutable;
use Module\Email\Contract\DeliveryLog;
use Module\Email\Contract\DeliveryLogCollection;
use Module\Email\Contract\DeliveryLogFilter;
use Module\Email\Contract\DeliveryProviderInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

final readonly class SweegoEmailDeliveryProvider implements DeliveryProviderInterface
{
    private const BASE_URL = 'https://api.sweego.io';

    private string $apiKey;

    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        #[Autowire(env: 'MAILER_DSN')]
        string $mailerDsn,
    ) {
        $parsed = parse_url($mailerDsn);
        $this->apiKey = urldecode($parsed['user'] ?? '');
    }

    public function isAvailable(): bool
    {
        return $this->apiKey !== '';
    }

    public function getLogs(DeliveryLogFilter $filter): DeliveryLogCollection
    {
        if (!$this->isAvailable()) {
            return new DeliveryLogCollection([], 0, $filter->offset, $filter->size);
        }

        try {
            $body = ['channel' => 'email', 'offset' => $filter->offset, 'size' => $filter->size];

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
                $body['start_date'] = $filter->since->format('Y-m-d');
            }
            if ($filter->until !== null) {
                $body['end_date'] = $filter->until->format('Y-m-d');
            }

            $response = $this->httpClient->request('POST', self::BASE_URL . '/logs/', [
                'headers' => $this->makeHeaders(),
                'json' => $body,
            ]);

            $data = $response->toArray();
            $items = array_map($this->mapLog(...), $data['result'] ?? []);

            if (count($items) === 0) {
                $this->logger->warning('Sweego logs API returned empty result', [
                    'filter' => (array) $filter,
                    'response_keys' => array_keys($data),
                    'response' => $data,
                ]);
            }

            return new DeliveryLogCollection($items, $data['nb_result_without_offset'] ?? count($items), $filter->offset, $filter->size);
        } catch (Throwable $e) {
            $this->logger->error('Sweego API request failed', [
                'message' => $e->getMessage(),
                'filter' => (array) $filter,
            ]);

            return new DeliveryLogCollection([], 0, $filter->offset, $filter->size);
        }
    }

    public function getLogByMessageId(string $messageId): ?DeliveryLog
    {
        $collection = $this->getLogs(new DeliveryLogFilter(messageId: $messageId, size: 1));

        return $collection->isEmpty() ? null : $collection->items[0];
    }

    private function mapLog(array $data): DeliveryLog
    {
        return new DeliveryLog(
            messageId: $data['transaction_id'] ?? $data['swg_uid'] ?? '',
            status: $data['status'] ?? 'unknown',
            recipientEmail: $data['email_to'] ?? '',
            createdAt: isset($data['email_creation']) ? new DateTimeImmutable($data['email_creation']) : new DateTimeImmutable(),
            updatedAt: isset($data['email_last_update']) ? new DateTimeImmutable($data['email_last_update']) : new DateTimeImmutable(),
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
