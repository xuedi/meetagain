<?php declare(strict_types=1);

namespace Plugin\Ranking\Enum;

enum Archetype: string
{
    case Elo = 'elo';
    case KyuDan = 'kyu_dan';
    case Belt = 'belt';
    case Division = 'division';
    case Points = 'points';

    public function label(): string
    {
        return match ($this) {
            self::Elo => 'ranking.archetype_elo',
            self::KyuDan => 'ranking.archetype_kyudan',
            self::Belt => 'ranking.archetype_belt',
            self::Division => 'ranking.archetype_division',
            self::Points => 'ranking.archetype_points',
        };
    }

    public function isNumeric(): bool
    {
        return $this === self::Elo || $this === self::Points;
    }

    public function hasTierList(): bool
    {
        return !$this->isNumeric();
    }
}
