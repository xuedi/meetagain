<?php declare(strict_types=1);

namespace App\Service\Notification\Admin;

use DateTimeImmutable;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag]
interface AdminNotificationProviderInterface
{
    public function getSection(): string;

    /**
     * @return AdminNotificationItem[]
     */
    public function getPendingItems(): array;

    public function getLatestPendingAt(): ?DateTimeImmutable;
}
