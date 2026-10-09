<?php declare(strict_types=1);

namespace App\Enum;

enum ModerationReportReason: string
{
    case Spam = 'spam';
    case Harassment = 'harassment';
    case Inappropriate = 'inappropriate';
    case Impersonation = 'impersonation';
    case Other = 'other';

    public function label(): string
    {
        return 'report.subject_reason_' . $this->value;
    }
}
