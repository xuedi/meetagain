<?php declare(strict_types=1);

namespace App\Service\Email\Delivery;

interface EmailDeliveryProviderInterface
{
    public function getLogs(EmailDeliveryLogFilter $filter): EmailDeliveryLogCollection;

    public function getLogByMessageId(string $messageId): ?EmailDeliveryLog;

    public function isAvailable(): bool;
}
