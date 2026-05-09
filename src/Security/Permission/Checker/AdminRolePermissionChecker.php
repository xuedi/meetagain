<?php declare(strict_types=1);

namespace App\Security\Permission\Checker;

use App\Security\Permission\Attribute\PermissionAttribute as Attr;
use App\Security\Permission\PermissionCheckerInterface;
use App\Security\Permission\PermissionContext;
use Override;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Decides domain-neutral admin attributes by enforcing a role floor.
 * Platform admins always allowed. ROLE_ORGANIZER required for event/cms/member/host/location
 * actions; ROLE_ADMIN required for system/email/elevated member actions.
 */
final class AdminRolePermissionChecker implements PermissionCheckerInterface
{
    private const array ORGANIZER_ATTRIBUTES = [
        Attr::EVENT_CREATE,
        Attr::EVENT_UPDATE,
        Attr::EVENT_CANCEL,
        Attr::EVENT_DELETE,
        Attr::HOST_CREATE,
        Attr::HOST_UPDATE,
        Attr::HOST_DELETE,
        Attr::LOCATION_CREATE,
        Attr::LOCATION_UPDATE,
        Attr::LOCATION_DELETE,
        Attr::CMS_PAGE_CREATE,
        Attr::CMS_PAGE_UPDATE,
        Attr::CMS_PAGE_DELETE,
        Attr::CMS_PAGE_PUBLISH,
        Attr::CMS_BLOCK_CREATE,
        Attr::CMS_BLOCK_UPDATE,
        Attr::CMS_BLOCK_DELETE,
        Attr::MEMBER_VIEW,
        Attr::MEMBER_UPDATE,
    ];

    private const array ADMIN_ATTRIBUTES = [
        Attr::MEMBER_DELETE,
        Attr::MEMBER_ROLE_UPDATE,
        Attr::MEMBER_STATUS_UPDATE,
        Attr::EMAIL_TEMPLATE_READ,
        Attr::EMAIL_TEMPLATE_UPDATE,
        Attr::EMAIL_BLOCKLIST_READ,
        Attr::EMAIL_BLOCKLIST_UPDATE,
        Attr::EMAIL_DEBUG_READ,
        Attr::EMAIL_PLANNED_READ,
        Attr::EMAIL_SENDLOG_READ,
        Attr::EMAIL_ANNOUNCEMENT_CREATE,
        Attr::EMAIL_ANNOUNCEMENT_UPDATE,
        Attr::EMAIL_ANNOUNCEMENT_DELETE,
        Attr::EMAIL_ANNOUNCEMENT_SEND,
        Attr::SYSTEM_LOGS_ACTIVITY_READ,
        Attr::SYSTEM_LOGS_CRON_READ,
        Attr::SYSTEM_LOGS_NOTFOUND_READ,
        Attr::SYSTEM_LOGS_SYSTEM_READ,
        Attr::SYSTEM_LOGS_PLATFORM_READ,
        Attr::SYSTEM_HEALTH_READ,
        Attr::SYSTEM_SETTINGS_READ,
        Attr::SYSTEM_SETTINGS_UPDATE,
        Attr::SYSTEM_LANGUAGE_READ,
        Attr::SYSTEM_LANGUAGE_UPDATE,
        Attr::SYSTEM_SITEMAP_READ,
        Attr::SYSTEM_REPORTS_READ,
        Attr::SYSTEM_SUPPORT_READ,
        Attr::SYSTEM_INTEGRITY_READ,
        Attr::SYSTEM_SECURITY_INCIDENTS_READ,
        Attr::SYSTEM_SECURITY_ACCESS_DENIED_READ,
        Attr::SYSTEM_SECURITY_RATE_LIMITING_READ,
        Attr::SYSTEM_SECURITY_PERMISSIONS_READ,
    ];

    public function __construct(
        private readonly Security $security,
    ) {}

    #[Override]
    public function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, self::ORGANIZER_ATTRIBUTES, true)
            || in_array($attribute, self::ADMIN_ATTRIBUTES, true);
    }

    #[Override]
    public function vote(string $attribute, PermissionContext $context): ?bool
    {
        if ($context->isAdmin) {
            return true;
        }

        if (in_array($attribute, self::ADMIN_ATTRIBUTES, true)) {
            return false;
        }

        return $this->security->isGranted('ROLE_ORGANIZER');
    }
}
