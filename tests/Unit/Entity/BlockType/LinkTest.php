<?php declare(strict_types=1);

namespace Tests\Unit\Entity\BlockType;

use App\Entity\BlockType\Link;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class LinkTest extends TestCase
{
    /** @return iterable<string, array{string, string}> */
    public static function linkProvider(): iterable
    {
        yield 'a relative path' => ['/events', '/events'];
        yield 'an anchor' => ['#join', '#join'];
        yield 'a path without a leading slash' => ['members', 'members'];
        yield 'an https link' => ['https://example.org/a?b=c', 'https://example.org/a?b=c'];
        yield 'an http link in capitals' => ['HTTP://example.org', 'HTTP://example.org'];
        yield 'a mail link' => ['mailto:club@example.org', 'mailto:club@example.org'];
        yield 'a phone link' => ['tel:+4930123456', 'tel:+4930123456'];
        yield 'padding is trimmed' => ['  /events  ', '/events'];
        yield 'a script link' => ['javascript:alert(1)', ''];
        yield 'a script link in mixed case' => ['JavaScript:alert(1)', ''];
        yield 'a script link split by a tab' => ["java\tscript:alert(1)", ''];
        yield 'a script link behind leading spaces' => ['  javascript:alert(1)', ''];
        yield 'a data link' => ['data:text/html,<script>alert(1)</script>', ''];
        yield 'a vbscript link' => ['vbscript:msgbox(1)', ''];
        yield 'an empty link' => ['', ''];
    }

    #[DataProvider('linkProvider')]
    public function testOnlyWebMailPhoneAndRelativeLinksSurvive(string $raw, string $expected): void
    {
        // Act
        $link = Link::fromString($raw);

        // Assert
        static::assertSame($expected, $link->href);
    }
}
