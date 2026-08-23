<?php declare(strict_types=1);

namespace App\Enum\CmsBlock;

enum MapHeight: string
{
    case Small = 'small';
    case Medium = 'medium';
    case Large = 'large';

    public function pixels(): string
    {
        return match ($this) {
            self::Small => '200px',
            self::Medium => '320px',
            self::Large => '480px',
        };
    }
}
