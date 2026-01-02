<?php declare(strict_types=1);

namespace App\Enum;

enum EmailType: string
{
    case VerificationRequest = 'verification_request';
    case Welcome = 'welcome';
    case PasswordResetRequest = 'password_reset_request';
    case NotificationRsvpAggregated = 'notification_rsvp_aggregated';
    case NotificationMessage = 'notification_message';
    case NotificationEventCanceled = 'notification_event_canceled';
    case Announcement = 'announcement';
}
