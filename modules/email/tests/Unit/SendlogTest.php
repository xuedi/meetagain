<?php declare(strict_types=1);

namespace Module\Email\Tests\Unit;

use DateTimeImmutable;
use Module\Email\Contract\QueueStatus;
use Module\Email\Internal\Entity\EmailQueue;
use Module\Email\Internal\Repository\EmailQueueRepository;
use Module\Email\Internal\Sendlog;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class SendlogTest extends TestCase
{
    public function testFindMapsTheRowAndKeepsTheEngineKeysOutOfTheContext(): void
    {
        // Arrange
        $repo = $this->createStub(EmailQueueRepository::class);
        $repo->method('find')->willReturn($this->row());

        // Act
        $sent = new Sendlog($repo)->find(7);

        // Assert
        static::assertNotNull($sent);
        static::assertSame(7, $sent->id);
        static::assertSame('welcome', $sent->template);
        static::assertSame('ann@example.com', $sent->recipient);
        static::assertSame(QueueStatus::Sent, $sent->status);
        static::assertSame(['name' => 'Ann'], $sent->context);
        static::assertSame(['siteName' => 'Site'], $sent->layout);
        static::assertEquals(new DateTimeImmutable('2031-01-01 10:00'), $sent->dispatchedAt);
    }

    public function testFindReturnsNullForAnUnknownId(): void
    {
        // Arrange
        $repo = $this->createStub(EmailQueueRepository::class);
        $repo->method('find')->willReturn(null);

        // Act
        $sent = new Sendlog($repo)->find(99);

        // Assert
        static::assertNull($sent);
    }

    public function testListHandsTheFiltersToTheRepository(): void
    {
        // Arrange
        $repo = $this->createMock(EmailQueueRepository::class);
        $repo->expects($this->once())->method('findFiltered')->with(5, null, 'welcome', 'ann@example.com', [QueueStatus::Pending])->willReturn([$this->row()]);

        // Act
        $list = new Sendlog($repo)->list(5, recipient: 'ann@example.com', template: 'welcome', status: QueueStatus::Pending);

        // Assert
        static::assertCount(1, $list);
        static::assertSame(7, $list[0]->id);
    }

    public function testStatsCountPendingAndStaleMessages(): void
    {
        // Arrange
        $repo = $this->createStub(EmailQueueRepository::class);
        $repo->method('getPendingCount')->willReturn(10);
        $repo->method('getStaleCount')->willReturn(2);

        // Act
        $stats = new Sendlog($repo)->stats();

        // Assert
        static::assertSame(10, $stats->pending);
        static::assertSame(2, $stats->stale);
    }

    private function row(): EmailQueue
    {
        $row = new EmailQueue()
            ->setTemplate('welcome')
            ->setSender('noreply@example.com')
            ->setRecipient('ann@example.com')
            ->setSubject('Welcome')
            ->setLang('en')
            ->setStatus(QueueStatus::Sent)
            ->setContext(['name' => 'Ann', '_layout' => ['siteName' => 'Site']])
            ->setCreatedAt(new DateTimeImmutable('2031-01-01 09:00'))
            ->setProviderDispatchedAt(new DateTimeImmutable('2031-01-01 10:00'));
        new ReflectionProperty(EmailQueue::class, 'id')->setValue($row, 7);

        return $row;
    }
}
