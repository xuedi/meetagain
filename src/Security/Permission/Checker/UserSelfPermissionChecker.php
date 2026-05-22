<?php declare(strict_types=1);

namespace App\Security\Permission\Checker;

use App\Entity\User;
use App\Security\Permission\Attribute\PermissionAttribute as Attr;
use App\Security\Permission\PermissionCheckerInterface;
use App\Security\Permission\PermissionContext;
use Override;

/**
 * Decides user.* attributes that act on the authenticated user themselves
 * (settings, password, blocks, profile images, messages). Platform admins
 * bypass; otherwise the actor must be the same User as the subject (or null
 * subject means "self").
 */
final class UserSelfPermissionChecker implements PermissionCheckerInterface
{
    private const array SELF_ATTRIBUTES = [
        Attr::USER_VIEW_SELF,
        Attr::USER_UPDATE_SELF,
        Attr::USER_PASSWORD_UPDATE,
        Attr::USER_BLOCK_UPDATE,
        Attr::USER_IMAGE_UPLOAD,
        Attr::USER_IMAGE_DELETE,
        Attr::USER_MESSAGE_READ,
        Attr::USER_MESSAGE_SEND,
    ];

    private const array ELEVATED_ATTRIBUTES = [
        Attr::USER_VIEW,
        Attr::USER_UPDATE,
    ];

    #[Override]
    public function supports(string $attribute, mixed $subject): bool
    {
        if (in_array($attribute, self::SELF_ATTRIBUTES, true)) {
            return $subject === null || $subject instanceof User;
        }

        return in_array($attribute, self::ELEVATED_ATTRIBUTES, true) && ($subject === null || $subject instanceof User);
    }

    #[Override]
    public function vote(string $attribute, PermissionContext $context): ?bool
    {
        if ($context->isAdmin) {
            return true;
        }

        if (in_array($attribute, self::ELEVATED_ATTRIBUTES, true)) {
            return false;
        }

        if ($context->actor === null) {
            return false;
        }

        $subject = $context->subject;
        if ($subject === null) {
            return true;
        }

        if (!$subject instanceof User) {
            return false;
        }

        return $subject->getId() === $context->actor->getId();
    }
}
