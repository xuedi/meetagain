<?php declare(strict_types=1);

namespace Module\Suggestion\Internal\Emails;

readonly class Approved extends ResolvedAbstract
{
    public const string IDENTIFIER = 'suggestion_approved';

    public function getIdentifier(): string
    {
        return self::IDENTIFIER;
    }

    public function getTriggerLabel(): string
    {
        return 'review_suggestion.trigger_approved';
    }
}
