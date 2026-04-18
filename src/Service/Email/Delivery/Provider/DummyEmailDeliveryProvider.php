<?php declare(strict_types=1);

namespace App\Service\Email\Delivery\Provider;

use App\Service\Email\Delivery\EmailDeliveryLog;
use App\Service\Email\Delivery\EmailDeliveryLogCollection;
use App\Service\Email\Delivery\EmailDeliveryLogFilter;
use App\Service\Email\Delivery\EmailDeliveryProviderInterface;

final readonly class DummyEmailDeliveryProvider implements EmailDeliveryProviderInterface
{
    public function isAvailable(): bool
    {
        return false;
    }

    public function getLogs(EmailDeliveryLogFilter $filter): EmailDeliveryLogCollection
    {
        return new EmailDeliveryLogCollection([], 0, 0, 0);
    }

    public function getLogByMessageId(string $messageId): ?EmailDeliveryLog
    {
        return null;
    }
}
