<?php declare(strict_types=1);

namespace App\Enum;

enum ImageIssueFilter: string
{
    case All = 'all';
    case Healthy = 'healthy';
    case MissingAlt = 'missing_alt';
    case MissingAttribution = 'missing_attribution';
    case Reported = 'reported';

    public function label(): string
    {
        return 'admin_system_images.issues_filter_' . $this->value;
    }
}
