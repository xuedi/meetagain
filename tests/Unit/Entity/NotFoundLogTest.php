<?php declare(strict_types=1);

namespace Tests\Unit\Entity;

use App\Entity\NotFoundLog;
use PHPUnit\Framework\TestCase;

class NotFoundLogTest extends TestCase
{
    public function testSetUrlTruncatesOverlongInputTo2048(): void
    {
        // Arrange
        $log = new NotFoundLog();
        $longUrl = str_repeat('a', 5000);

        // Act
        $log->setUrl($longUrl);

        // Assert
        static::assertSame(2048, mb_strlen((string) $log->getUrl()));
    }

    public function testSetUrlPreservesShortInput(): void
    {
        // Arrange
        $log = new NotFoundLog();
        $url = '/some/short/path';

        // Act
        $log->setUrl($url);

        // Assert
        static::assertSame($url, $log->getUrl());
    }

    public function testSetUserAgentTruncatesTo512(): void
    {
        // Arrange
        $log = new NotFoundLog();

        // Act
        $log->setUserAgent(str_repeat('x', 1000));

        // Assert
        static::assertSame(512, mb_strlen((string) $log->getUserAgent()));
    }

    public function testSetUserAgentNullStaysNull(): void
    {
        // Arrange + Act
        $log = new NotFoundLog();
        $log->setUserAgent(null);

        // Assert
        static::assertNull($log->getUserAgent());
    }

    public function testSetRefererTruncatesTo2048(): void
    {
        // Arrange
        $log = new NotFoundLog();

        // Act
        $log->setReferer(str_repeat('x', 5000));

        // Assert
        static::assertSame(2048, mb_strlen((string) $log->getReferer()));
    }
}
