<?php declare(strict_types=1);

namespace Tests\Unit\Entity;

use App\Entity\RateLimitLog;
use PHPUnit\Framework\TestCase;

class RateLimitLogTest extends TestCase
{
    public function testSetUrlTruncatesTo2048(): void
    {
        // Arrange
        $log = new RateLimitLog();

        // Act
        $log->setUrl(str_repeat('x', 5000));

        // Assert
        static::assertSame(2048, mb_strlen($log->getUrl()));
    }

    public function testSetLimiterTruncatesTo64(): void
    {
        // Arrange
        $log = new RateLimitLog();

        // Act
        $log->setLimiter(str_repeat('l', 200));

        // Assert
        static::assertSame(64, mb_strlen($log->getLimiter()));
    }

    public function testSetUserIdentifierLowercasesAndTruncates(): void
    {
        // Arrange
        $log = new RateLimitLog();

        // Act
        $log->setUserIdentifier('USER@EXAMPLE.COM');

        // Assert
        static::assertSame('user@example.com', $log->getUserIdentifier());
    }

    public function testSetUserIdentifierNullStaysNull(): void
    {
        // Arrange + Act
        $log = new RateLimitLog();
        $log->setUserIdentifier(null);

        // Assert
        static::assertNull($log->getUserIdentifier());
    }
}
