<?php declare(strict_types=1);

namespace Tests\Unit\Metrics\Gauge;

use App\Metrics\Gauge\EmailQueueGauge;
use App\Repository\EmailQueueRepository;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class EmailQueueGaugeTest extends TestCase
{
    public function testQueueBacklogIsReported(): void
    {
        // Arrange
        $repository = $this->createStub(EmailQueueRepository::class);
        $repository->method('getPendingCount')->willReturn(4);
        $repository->method('findOldestPendingCreatedAt')->willReturn(new DateTimeImmutable('-10 minutes'));
        $repository->method('getDeliveryStats')->willReturn(['total' => 9, 'sent' => 7, 'failed' => 2]);
        $gauge = new EmailQueueGauge($repository);

        // Act
        $point = iterator_to_array($gauge->collect(), false)[0];

        // Assert
        static::assertSame('email_queue', $point->measurement);
        static::assertSame(4, $point->fields['pending']);
        static::assertEqualsWithDelta(600, $point->fields['oldest_pending_age_s'], 5);
        static::assertSame(2, $point->fields['failed_24h']);
    }

    public function testEmptyQueueHasNoAge(): void
    {
        // Arrange
        $repository = $this->createStub(EmailQueueRepository::class);
        $repository->method('findOldestPendingCreatedAt')->willReturn(null);
        $repository->method('getDeliveryStats')->willReturn(['total' => 0, 'sent' => 0, 'failed' => 0]);
        $gauge = new EmailQueueGauge($repository);

        // Act
        $point = iterator_to_array($gauge->collect(), false)[0];

        // Assert
        static::assertSame(0, $point->fields['oldest_pending_age_s']);
    }
}
