<?php declare(strict_types=1);

namespace App\Service\Email\Delivery;

interface EmailDeliveryProviderInterface
{
    public function getLogs(LogFilter $filter): LogCollection;

    public function getLogByMessageId(string $messageId): ?Log;

    public function isAvailable(): bool;
}
