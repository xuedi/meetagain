<?php declare(strict_types=1);

namespace App\Service\Email\Delivery;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final readonly class ProviderChain
{
    /** @param iterable<EmailDeliveryProviderInterface> $providers */
    public function __construct(
        #[AutowireIterator(EmailDeliveryProviderInterface::class)]
        private iterable $providers,
    ) {}

    public function isAvailable(): bool
    {
        return $this->claim() !== null;
    }

    public function getLogs(LogFilter $filter): LogCollection
    {
        return $this->claim()?->getLogs($filter) ?? new LogCollection([], 0, $filter->offset, $filter->size);
    }

    public function getLogByMessageId(string $messageId): ?Log
    {
        return $this->claim()?->getLogByMessageId($messageId);
    }

    private function claim(): ?EmailDeliveryProviderInterface
    {
        foreach ($this->providers as $provider) {
            if ($provider->isAvailable()) {
                return $provider;
            }
        }

        return null;
    }
}
