<?php declare(strict_types=1);

namespace Module\Suggestion\Contract;

enum Status: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'review_suggestion.status_pending',
            self::Approved => 'review_suggestion.status_approved',
            self::Rejected => 'review_suggestion.status_rejected',
            self::Withdrawn => 'review_suggestion.status_withdrawn',
        };
    }
}
