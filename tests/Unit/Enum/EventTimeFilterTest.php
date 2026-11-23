<?php declare(strict_types=1);

namespace Tests\Unit\Enum;

use App\Enum\EventTimeFilter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class EventTimeFilterTest extends TestCase
{
    /** @return iterable<string, array{EventTimeFilter, string}> */
    public static function titleKeyProvider(): iterable
    {
        yield 'upcoming' => [EventTimeFilter::Future, 'events.page_title_listing'];
        yield 'past' => [EventTimeFilter::Past, 'events.page_title_listing_past'];
        yield 'all' => [EventTimeFilter::All, 'events.page_title_listing_all'];
    }

    #[DataProvider('titleKeyProvider')]
    public function testEachTimeFilterNamesItsOwnPageTitle(EventTimeFilter $filter, string $expected): void
    {
        // Act
        $key = $filter->pageTitleKey();

        // Assert
        static::assertSame($expected, $key);
    }
}
