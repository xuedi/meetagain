<?php declare(strict_types=1);

namespace App\Service\Notification\Admin;

use App\Entity\User;
use DateTimeImmutable;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Contracts\Translation\TranslatableInterface;

/**
 * Supplies one section of the admin summary email. The section and its items are collected once per recipient and
 * translated into that recipient's language by the consumer, so implementations return translatable messages
 * rather than translating themselves.
 */
#[AutoconfigureTag]
interface AdminNotificationProviderInterface
{
    public function getSection(): string|TranslatableInterface;

    /**
     * @return AdminNotificationItem[]
     */
    public function getPendingItems(User $recipient): array;

    public function getLatestPendingAt(): ?DateTimeImmutable;
}
