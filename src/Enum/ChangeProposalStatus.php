<?php declare(strict_types=1);

namespace App\Enum;

enum ChangeProposalStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'review.status_pending',
            self::Approved => 'review.status_approved',
            self::Rejected => 'review.status_rejected',
            self::Withdrawn => 'review.status_withdrawn',
        };
    }
}
