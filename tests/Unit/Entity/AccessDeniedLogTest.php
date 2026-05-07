<?php declare(strict_types=1);

namespace Tests\Unit\Entity;

use App\Entity\AccessDeniedLog;
use PHPUnit\Framework\TestCase;

class AccessDeniedLogTest extends TestCase
{
    public function testSetUrlTruncatesTo2048(): void
    {
        // Arrange
        $log = new AccessDeniedLog();

        // Act
        $log->setUrl(str_repeat('x', 5000));

        // Assert
        static::assertSame(2048, mb_strlen($log->getUrl()));
    }

    public function testSetReasonTruncatesTo64(): void
    {
        // Arrange
        $log = new AccessDeniedLog();

        // Act
        $log->setReason(str_repeat('r', 200));

        // Assert
        static::assertSame(64, mb_strlen($log->getReason()));
    }

    public function testSetUserAgentTruncatesTo512(): void
    {
        // Arrange
        $log = new AccessDeniedLog();

        // Act
        $log->setUserAgent(str_repeat('u', 1000));

        // Assert
        static::assertSame(512, mb_strlen((string) $log->getUserAgent()));
    }
}
