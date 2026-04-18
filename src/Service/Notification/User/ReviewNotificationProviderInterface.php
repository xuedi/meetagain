<?php

declare(strict_types=1);

namespace App\Service\Notification\User;

use App\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

#[AutoconfigureTag]
interface ReviewNotificationProviderInterface
{
    /**
     * Stable snake_case string unique to this provider (e.g. 'bookclub.book_approval').
     * Embedded in form action URLs — must never change after deployment.
     */
    public function getIdentifier(): string;

    /**
     * Returns items this user may act on. Must filter by role internally; return [] if not authorised.
     *
     * @return ReviewNotificationItem[]
     */
    public function getReviewItems(User $user): array;

    /**
     * Executes the approval action for the given item. Must verify authorisation internally.
     *
     * @throws AccessDeniedException if $user is not authorised
     */
    public function approveItem(User $user, string $itemId): void;

    /**
     * Executes the deny/reject action for the given item. Must verify authorisation internally.
     *
     * @throws AccessDeniedException if $user is not authorised
     */
    public function denyItem(User $user, string $itemId): void;
}
