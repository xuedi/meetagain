<?php declare(strict_types=1);

namespace App\Enum;

enum AccountDeletionReason: string
{
    case RatherNotSay = 'rather_not_say';
    case JustTryingOut = 'just_trying_out';
    case NoEventsNearby = 'no_events_nearby';
    case TooLittleActivity = 'too_little_activity';
    case NoTime = 'no_time';
    case Moved = 'moved';
    case TooManyEmails = 'too_many_emails';
    case PrivacyConcerns = 'privacy_concerns';
    case HardToUse = 'hard_to_use';
    case TechnicalProblems = 'technical_problems';
    case MissingFeatures = 'missing_features';
    case FoundAlternative = 'found_alternative';
    case BadExperience = 'bad_experience';
    case DuplicateAccount = 'duplicate_account';
    case Other = 'other';

    public function label(): string
    {
        return 'profile_config.delete_reason_' . $this->value;
    }
}
