<?php declare(strict_types=1);

namespace Plugin\Fediverse\Enum;

enum Content: string
{
    case Title = 'title';
    case Summary = 'summary';
    case Full = 'full';

    public function label(): string
    {
        return match ($this) {
            self::Title => 'fediverse_config.content_title',
            self::Summary => 'fediverse_config.content_summary',
            self::Full => 'fediverse_config.content_full',
        };
    }

    public function atMost(self $ceiling): self
    {
        return $this->rank() <= $ceiling->rank() ? $this : $ceiling;
    }

    /** @return list<self> */
    public static function upTo(self $ceiling): array
    {
        return array_values(array_filter(self::cases(), static fn(self $case): bool => $case->rank() <= $ceiling->rank()));
    }

    private function rank(): int
    {
        return match ($this) {
            self::Title => 0,
            self::Summary => 1,
            self::Full => 2,
        };
    }
}
