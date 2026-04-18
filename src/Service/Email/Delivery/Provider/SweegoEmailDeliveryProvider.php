<?php declare(strict_types=1);

namespace App\Service\Email\Delivery\Provider;

use App\Service\Email\Delivery\EmailDeliveryLog;
use App\Service\Email\Delivery\EmailDeliveryLogCollection;
use App\Service\Email\Delivery\EmailDeliveryLogFilter;
use App\Service\Email\Delivery\EmailDeliveryProviderInterface;
use DateTimeImmutable;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

#[AsAlias(EmailDeliveryProviderInterface::class)]
final readonly class SweegoEmailDeliveryProvider implements EmailDeliveryProviderInterface
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

    public function getLogs(EmailDeliveryLogFilter $filter): EmailDeliveryLogCollection
    {
        if (!$this->isAvailable()) {
            return new EmailDeliveryLogCollection([], 0, $filter->offset, $filter->size);
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

            return new EmailDeliveryLogCollection(
                $items,
                $data['nb_result_without_offset'] ?? count($items),
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
