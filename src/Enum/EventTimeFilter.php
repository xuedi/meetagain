<?php declare(strict_types=1);

namespace App\Enum;

enum EventTimeFilter: int
{
    case All = 1;
    case Past = 2;
    case Future = 3;

    public function pageTitleKey(): string
    {
        return match ($this) {
            self::All => 'events.page_title_listing_all',
            self::Past => 'events.page_title_listing_past',
            self::Future => 'events.page_title_listing',
        };
    }

    public function headingKey(): string
    {
        return match ($this) {
            self::All => 'events.heading_listing_all',
            self::Past => 'events.heading_listing_past',
            self::Future => 'events.heading_listing',
        };
    }
}
