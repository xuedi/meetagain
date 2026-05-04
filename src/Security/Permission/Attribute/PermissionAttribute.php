<?php declare(strict_types=1);

namespace App\Security\Permission\Attribute;

final class PermissionAttribute
{
    public const string EVENT_VIEW = 'event.view';
    public const string EVENT_RSVP = 'event.rsvp';
    public const string EVENT_COMMENT_CREATE = 'event.comment.create';
    public const string EVENT_COMMENT_DELETE = 'event.comment.delete';
    public const string EVENT_IMAGE_UPLOAD = 'event.image.upload';
    public const string EVENT_IMAGE_DELETE = 'event.image.delete';

    public const string USER_VIEW = 'user.view';
    public const string USER_VIEW_SELF = 'user.view.self';
    public const string USER_UPDATE = 'user.update';

    private function __construct() {}
}
