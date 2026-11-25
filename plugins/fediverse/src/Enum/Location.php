<?php declare(strict_types=1);

namespace Plugin\Fediverse\Enum;

enum Location: string
{
    case None = 'none';
    case City = 'city';
    case Address = 'address';

    public function label(): string
    {
        return match ($this) {
            self::None => 'fediverse_config.location_none',
            self::City => 'fediverse_config.location_city',
            self::Address => 'fediverse_config.location_address',
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
            self::None => 0,
            self::City => 1,
            self::Address => 2,
        };
    }
}
