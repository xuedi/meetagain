<?php declare(strict_types=1);

namespace App\Entity;

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
