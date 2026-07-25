<?php declare(strict_types=1);

namespace App\Enum;

enum CanonicalLaneSegmentType: string
{
    case First = 'first';
    case Root = 'root';
    case Detached = 'detached';
    case Follower = 'follower';

    public function label(): string
    {
        return match ($this) {
            self::First => 'admin_seo_canonical.chip_first',
            self::Root => 'admin_seo_canonical.chip_root',
            self::Detached => 'admin_seo_canonical.chip_detached',
            self::Follower => 'admin_seo_canonical.chip_followers',
        };
    }
}
