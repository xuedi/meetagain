<?php declare(strict_types=1);

namespace App\Enum;

use Symfony\Contracts\Translation\TranslatorInterface;

enum ActivityType: int
{
    case ChangedUsername = 0;
    case Login = 1;
    case RsvpYes = 2;
    case RsvpNo = 3;
    case Registered = 4;
    case FollowedUser = 5;
    case UnFollowedUser = 6;
    case PasswordResetRequest = 7;
    case PasswordReset = 8;
    case EventImageUploaded = 9;
    case ReportedImage = 10;
    case SendMessage = 11;
    case RegistrationEmailConfirmed = 12;
    case UpdatedProfilePicture = 13;
    case BlockedUser = 14;
    case UnblockedUser = 15;
    case PasswordChanged = 16;
    case CommentedOnEvent = 17;
    case RegistrationEmailResent = 18;
    case AdminEventCreated = 19;
    case AdminEventEdited = 20;
    case AdminEventDeleted = 21;
    case AdminEventCancelled = 22;
    case AdminCmsPageCreated = 23;
    case AdminCmsPageUpdated = 24;
    case AdminCmsPageDeleted = 25;
    case AdminMemberApproved = 26;
    case AdminMemberDenied = 27;
    case AdminMemberPromoted = 28;

    // TODO: should be separate translator not here in enum
    public static function getChoices(TranslatorInterface $translator): array
    {
        return [
            $translator->trans('ChangedUsername') => self::ChangedUsername,
            $translator->trans('Login') => self::Login,
            $translator->trans('RsvpYes') => self::RsvpYes,
            $translator->trans('RsvpNo') => self::RsvpNo,
            // TODO: send message, did rsvp, wrote comment
        ];
    }
}
