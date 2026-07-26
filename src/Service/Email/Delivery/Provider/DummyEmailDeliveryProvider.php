<?php declare(strict_types=1);

namespace App\Service\Email\Delivery\Provider;

use App\Service\Email\Delivery\Log;
use App\Service\Email\Delivery\LogCollection;
use App\Service\Email\Delivery\LogFilter;
use App\Service\Email\Delivery\EmailDeliveryProviderInterface;

final readonly class DummyEmailDeliveryProvider implements EmailDeliveryProviderInterface
{
    public function isAvailable(): bool
    {
        return false;
    }

    public function getLogs(LogFilter $filter): LogCollection
    {
        return new LogCollection([], 0, 0, 0);
    }

    public function getLogByMessageId(string $messageId): ?Log
    {
        return null;
    }
}
