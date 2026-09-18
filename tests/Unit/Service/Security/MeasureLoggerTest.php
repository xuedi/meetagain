<?php declare(strict_types=1);

namespace Tests\Unit\Service\Security;

use App\Entity\SecurityMeasureLog;
use App\Enum\SecurityMeasure;
use App\Enum\SecurityMeasureOutcome;
use App\Repository\SecurityMeasureLogRepository;
use App\Service\Security\MeasureLogger;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Request;

final class MeasureLoggerTest extends TestCase
{
    public function testABlockIsStoredAsItsOwnRowWithTheRequestDetails(): void
    {
        // Arrange
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $stored = null;
        $entityManager
            ->method('persist')
            ->willReturnCallback(static function (object $entity) use (&$stored): void {
                $stored = $entity;
            });

        $request = Request::create('/en/register', 'POST', server: ['REMOTE_ADDR' => '203.0.113.7', 'HTTP_USER_AGENT' => 'curl/8']);
        $logger = new MeasureLogger($this->createStub(SecurityMeasureLogRepository::class), $entityManager, new MockClock('2026-09-18 14:30:00'));

        // Act
        $logger->recordBlock(SecurityMeasure::Honeypot, 'app_register', $request, ['reason' => 'filled']);

        // Assert
        static::assertInstanceOf(SecurityMeasureLog::class, $stored);
        static::assertSame(SecurityMeasure::Honeypot, $stored->getMeasure());
        static::assertSame(SecurityMeasureOutcome::Blocked, $stored->getOutcome());
        static::assertSame(1, $stored->getCount());
        static::assertSame('app_register', $stored->getContext());
        static::assertSame('203.0.113.7', $stored->getIp());
        static::assertSame('curl/8', $stored->getUserAgent());
        static::assertSame(['reason' => 'filled'], $stored->getDetail());
        static::assertSame('2026-09-18', $stored->getDay()->format('Y-m-d'));
    }

    public function testTheFirstPassOfADayCreatesTheCounterRow(): void
    {
        // Arrange
        $repo = $this->createStub(SecurityMeasureLogRepository::class);
        $repo->method('incrementPassCounter')->willReturn(0);

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $stored = null;
        $entityManager
            ->method('persist')
            ->willReturnCallback(static function (object $entity) use (&$stored): void {
                $stored = $entity;
            });

        $logger = new MeasureLogger($repo, $entityManager, new MockClock('2026-09-18 14:30:00'));

        // Act
        $logger->recordPass(SecurityMeasure::SubmitTiming, 'app_contact');

        // Assert
        static::assertInstanceOf(SecurityMeasureLog::class, $stored);
        static::assertSame(SecurityMeasureOutcome::Passed, $stored->getOutcome());
        static::assertSame(1, $stored->getCount());
        static::assertNull($stored->getIp());
    }

    public function testAFurtherPassIncrementsTheExistingCounterWithoutANewRow(): void
    {
        // Arrange
        $repo = $this->createStub(SecurityMeasureLogRepository::class);
        $repo->method('incrementPassCounter')->willReturn(1);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(static::never())->method('persist');

        $logger = new MeasureLogger($repo, $entityManager, new MockClock('2026-09-18 14:30:00'));

        // Act
        $logger->recordPass(SecurityMeasure::SubmitTiming, 'app_contact');
    }

    public function testPurgeDeletesEverythingOlderThanTheRetentionWindow(): void
    {
        // Arrange
        $repo = $this->createMock(SecurityMeasureLogRepository::class);
        $repo
            ->expects(static::once())
            ->method('deleteOlderThan')
            ->with(static::callback(static fn($cutoff): bool => $cutoff->format('Y-m-d H:i:s') === '2026-08-19 00:00:00'))
            ->willReturn(12);

        $logger = new MeasureLogger($repo, $this->createStub(EntityManagerInterface::class), new MockClock('2026-09-18 14:30:00'));

        // Act
        $deleted = $logger->purgeOlderThan(30);

        // Assert
        static::assertSame(12, $deleted);
    }
}
