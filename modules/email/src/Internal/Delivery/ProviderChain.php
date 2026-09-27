<?php declare(strict_types=1);

namespace Module\Email\Internal\Delivery;

use Module\Email\Contract\DeliveryLog;
use Module\Email\Contract\DeliveryLogCollection;
use Module\Email\Contract\DeliveryLogFilter;
use Module\Email\Contract\DeliveryProviderInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final readonly class ProviderChain
{
    /** @param iterable<DeliveryProviderInterface> $providers */
    public function __construct(
        #[AutowireIterator(DeliveryProviderInterface::class)]
        private iterable $providers,
    ) {}

    public function isAvailable(): bool
    {
        return $this->claim() !== null;
    }

    public function getLogs(DeliveryLogFilter $filter): DeliveryLogCollection
    {
        return $this->claim()?->getLogs($filter) ?? new DeliveryLogCollection([], 0, $filter->offset, $filter->size);
    }

    public function getLogByMessageId(string $messageId): ?DeliveryLog
    {
        return $this->claim()?->getLogByMessageId($messageId);
    }

    private function claim(): ?DeliveryProviderInterface
    {
        foreach ($this->providers as $provider) {
            if ($provider->isAvailable()) {
                return $provider;
            }
        }

        return null;
    }
}
