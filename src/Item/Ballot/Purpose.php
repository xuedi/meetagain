<?php declare(strict_types=1);

namespace App\Item\Ballot;

final readonly class Purpose
{
    public const string PREFIX = 'event.item.';
    public const string SUBJECT_TYPE = 'event';

    public function forType(string $itemType): string
    {
        return self::PREFIX . $itemType;
    }

    public function itemTypeOf(string $purpose): ?string
    {
        if (!str_starts_with($purpose, self::PREFIX)) {
            return null;
        }

        $itemType = substr($purpose, strlen(self::PREFIX));

        return $itemType === '' ? null : $itemType;
    }
}
