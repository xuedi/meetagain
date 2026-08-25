<?php declare(strict_types=1);

namespace Module\Trust\Contract;

enum TrustLevel: string
{
    case Slight = 'slight';
    case Trusted = 'trusted';
    case Absolute = 'absolute';

    public function label(): string
    {
        return match ($this) {
            self::Slight => 'trust.level_slight',
            self::Trusted => 'trust.level_trusted',
            self::Absolute => 'trust.level_absolute',
        };
    }
}
