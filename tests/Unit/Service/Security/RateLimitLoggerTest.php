<?php

declare(strict_types=1);

namespace Tests\Unit\Service\Security;

use App\Entity\RateLimitLog;
use App\Service\Security\RateLimitLogger;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;

#[AllowMockObjectsWithoutExpectations]
class RateLimitLoggerTest extends TestCase
{
    public function testLogPersistsExpectedFields(): void
    {
        // Arrange
        $captured = null;
        $em = $this->createMock(EntityManagerInterface::class);
        $em
            ->expects(self::once())
            ->method('persist')
            ->willReturnCallback(static function (RateLimitLog $log) use (&$captured): void {
                $captured = $log;
            });
        $em->expects(self::once())->method('flush');

        $logger = $this->createMock(LoggerInterface::class);
        $service = new RateLimitLogger($em, $logger);
        $request = Request::create('/register', 'POST');
        $request->headers->set('User-Agent', 'curl/8.0');
        $request->server->set('REMOTE_ADDR', '203.0.113.10');

        // Act
        $service->log('registration', $request, 'User@Example.com');

        // Assert
        self::assertInstanceOf(RateLimitLog::class, $captured);
        self::assertSame('registration', $captured->getLimiter());
        self::assertSame('203.0.113.10', $captured->getIp());
        self::assertSame('curl/8.0', $captured->getUserAgent());
        self::assertSame('user@example.com', $captured->getUserIdentifier());
        self::assertStringContainsString('/register', $captured->getUrl());
    }

    public function testLogSwallowsEmExceptions(): void
    {
        // Arrange
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('persist')->willThrowException(new RuntimeException('db down'));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning');
        $service = new RateLimitLogger($em, $logger);
        $request = Request::create('/contact');

        // Act + Assert: no exception bubbles up
        $service->log('support', $request);
        self::assertTrue(true);
    }
}
