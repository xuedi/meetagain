<?php declare(strict_types=1);

namespace App\Service\Email\Provider\Dummy;

use App\Service\Email\Provider\EmailDeliveryLog;
use App\Service\Email\Provider\EmailDeliveryLogCollection;
use App\Service\Email\Provider\EmailDeliveryLogFilter;
use App\Service\Email\Provider\EmailDeliveryProviderInterface;

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
