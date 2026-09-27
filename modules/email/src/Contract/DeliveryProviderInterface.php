<?php declare(strict_types=1);

namespace Module\Email\Contract;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag]
interface DeliveryProviderInterface
{
    public function getLogs(DeliveryLogFilter $filter): DeliveryLogCollection;

    public function getLogByMessageId(string $messageId): ?DeliveryLog;

    public function isAvailable(): bool;
}
