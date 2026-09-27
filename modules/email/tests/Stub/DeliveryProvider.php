<?php declare(strict_types=1);

namespace Module\Email\Tests\Stub;

use DateTimeImmutable;
use Module\Email\Contract\DeliveryLog;
use Module\Email\Contract\DeliveryLogCollection;
use Module\Email\Contract\DeliveryLogFilter;
use Module\Email\Contract\DeliveryProviderInterface;
use Override;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(priority: 100)]
final class DeliveryProvider implements DeliveryProviderInterface
{
    public string $status = 'delivered';

    #[Override]
    public function getLogs(DeliveryLogFilter $filter): DeliveryLogCollection
    {
        return new DeliveryLogCollection([], 0, $filter->offset, $filter->size);
    }

    #[Override]
    public function getLogByMessageId(string $messageId): ?DeliveryLog
    {
        $now = new DateTimeImmutable();

        return new DeliveryLog($messageId, $this->status, 'recipient@module-test.example', $now, $now, null, null);
    }

    #[Override]
    public function isAvailable(): bool
    {
        return true;
    }
}
