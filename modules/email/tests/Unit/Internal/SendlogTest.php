<?php declare(strict_types=1);

namespace Module\Email\Tests\Unit\Internal;

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
