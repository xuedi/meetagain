<?php declare(strict_types=1);

namespace App\Security\Permission\Attribute;

/**
 * @codeCoverageIgnore
 */
final class PermissionAttribute
{
    public const string EVENT_VIEW = 'event.view';
    public const string EVENT_RSVP = 'event.rsvp';
    public const string EVENT_COMMENT_CREATE = 'event.comment.create';
    public const string EVENT_COMMENT_DELETE = 'event.comment.delete';
    public const string EVENT_IMAGE_UPLOAD = 'event.image.upload';
    public const string EVENT_IMAGE_DELETE = 'event.image.delete';

    public const string EVENT_CREATE = 'event.create';
    public const string EVENT_UPDATE = 'event.update';
    public const string EVENT_CANCEL = 'event.cancel';
    public const string EVENT_DELETE = 'event.delete';

    public const string HOST_CREATE = 'host.create';
    public const string HOST_UPDATE = 'host.update';
    public const string HOST_DELETE = 'host.delete';

    public const string LOCATION_CREATE = 'location.create';
    public const string LOCATION_UPDATE = 'location.update';
    public const string LOCATION_DELETE = 'location.delete';

    public const string CMS_PAGE_CREATE = 'cms.page.create';
    public const string CMS_PAGE_UPDATE = 'cms.page.update';
    public const string CMS_PAGE_DELETE = 'cms.page.delete';
    public const string CMS_PAGE_PUBLISH = 'cms.page.publish';
    public const string CMS_BLOCK_CREATE = 'cms.block.create';
    public const string CMS_BLOCK_UPDATE = 'cms.block.update';
    public const string CMS_BLOCK_DELETE = 'cms.block.delete';

    public const string MEMBER_VIEW = 'member.view';
    public const string MEMBER_UPDATE = 'member.update';
    public const string MEMBER_DELETE = 'member.delete';
    public const string MEMBER_ROLE_UPDATE = 'member.role.update';
    public const string MEMBER_STATUS_UPDATE = 'member.status.update';

    public const string EMAIL_TEMPLATE_READ = 'email.template.read';
    public const string EMAIL_TEMPLATE_UPDATE = 'email.template.update';
    public const string EMAIL_BLOCKLIST_READ = 'email.blocklist.read';
    public const string EMAIL_BLOCKLIST_UPDATE = 'email.blocklist.update';
    public const string EMAIL_DEBUG_READ = 'email.debug.read';
    public const string EMAIL_PLANNED_READ = 'email.planned.read';
    public const string EMAIL_SENDLOG_READ = 'email.sendlog.read';
    public const string EMAIL_ANNOUNCEMENT_CREATE = 'email.announcement.create';
    public const string EMAIL_ANNOUNCEMENT_UPDATE = 'email.announcement.update';
    public const string EMAIL_ANNOUNCEMENT_DELETE = 'email.announcement.delete';
    public const string EMAIL_ANNOUNCEMENT_SEND = 'email.announcement.send';

    public const string SYSTEM_LOGS_ACTIVITY_READ = 'system.logs.activity.read';
    public const string SYSTEM_LOGS_CRON_READ = 'system.logs.cron.read';
    public const string SYSTEM_LOGS_NOTFOUND_READ = 'system.logs.notfound.read';
    public const string SYSTEM_LOGS_SYSTEM_READ = 'system.logs.system.read';
    public const string SYSTEM_LOGS_PLATFORM_READ = 'system.logs.platform.read';
    public const string SYSTEM_HEALTH_READ = 'system.health.read';
    public const string SYSTEM_SETTINGS_READ = 'system.settings.read';
    public const string SYSTEM_SETTINGS_UPDATE = 'system.settings.update';
    public const string SYSTEM_LANGUAGE_READ = 'system.language.read';
    public const string SYSTEM_LANGUAGE_UPDATE = 'system.language.update';
    public const string SYSTEM_SITEMAP_READ = 'system.sitemap.read';
    public const string SYSTEM_IMAGES_ALT_READ = 'system.images.alt.read';
    public const string SYSTEM_IMAGES_ALT_UPDATE = 'system.images.alt.update';
    public const string SYSTEM_REPORTS_READ = 'system.reports.read';
    public const string SYSTEM_SUPPORT_READ = 'system.support.read';
    public const string SYSTEM_INTEGRITY_READ = 'system.integrity.read';
    public const string SYSTEM_SECURITY_INCIDENTS_READ = 'system.security.incidents.read';
    public const string SYSTEM_SECURITY_ACCESS_DENIED_READ = 'system.security.access_denied.read';
    public const string SYSTEM_SECURITY_RATE_LIMITING_READ = 'system.security.rate_limiting.read';
    public const string SYSTEM_SECURITY_PERMISSIONS_READ = 'system.security.permissions.read';

    public const string USER_VIEW = 'user.view';
    public const string USER_VIEW_SELF = 'user.view.self';
    public const string USER_UPDATE = 'user.update';
    public const string USER_UPDATE_SELF = 'user.update.self';
    public const string USER_PASSWORD_UPDATE = 'user.password.update';
    public const string USER_BLOCK_UPDATE = 'user.block.update';
    public const string USER_IMAGE_UPLOAD = 'user.image.upload';
    public const string USER_IMAGE_DELETE = 'user.image.delete';
    public const string USER_MESSAGE_READ = 'user.message.read';
    public const string USER_MESSAGE_SEND = 'user.message.send';

    public const string DEVELOPER_APP_VIEW_SELF = 'developer_app.view.self';
    public const string DEVELOPER_APP_MANAGE_SELF = 'developer_app.manage.self';
    public const string DEVELOPER_APP_REVIEW = 'developer_app.review';
    public const string DEVELOPER_APP_REVOKE = 'developer_app.revoke';

    private function __construct() {}
}
