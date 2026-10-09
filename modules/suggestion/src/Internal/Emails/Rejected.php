<?php declare(strict_types=1);

namespace Module\Suggestion\Internal\Emails;

readonly class Rejected extends ResolvedAbstract
{
    public const string IDENTIFIER = 'suggestion_rejected';

    public function getIdentifier(): string
    {
        return self::IDENTIFIER;
    }

    public function getTriggerLabel(): string
    {
        return 'review_suggestion.trigger_rejected';
    }
}
