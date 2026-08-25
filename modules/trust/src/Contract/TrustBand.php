<?php declare(strict_types=1);

namespace Module\Trust\Contract;

enum TrustBand: string
{
    case Newcomer = 'newcomer';
    case Known = 'known';
    case Trusted = 'trusted';
    case Highly = 'highly';

    public function label(): string
    {
        return match ($this) {
            self::Newcomer => 'trust.band_newcomer',
            self::Known => 'trust.band_known',
            self::Trusted => 'trust.band_trusted',
            self::Highly => 'trust.band_highly',
        };
    }
}
