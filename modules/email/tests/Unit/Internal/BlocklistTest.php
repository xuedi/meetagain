<?php declare(strict_types=1);

namespace Module\Email\Tests\Unit\Internal;

use Doctrine\ORM\EntityManagerInterface;
use Module\Email\Internal\Blocklist;
use Module\Email\Internal\Entity\EmailBlocklistEntry;
use Module\Email\Internal\Repository\EmailBlocklistRepository;
use PHPUnit\Framework\TestCase;

final class BlocklistTest extends TestCase
{
    public function testCachePreventsSecondLoad(): void
    {
        $repo = $this->createMock(EmailBlocklistRepository::class);
        $repo->expects($this->once())->method('findAllOrdered')->willReturn([]);

        $checker = new Blocklist($repo, $this->createStub(EntityManagerInterface::class));

        $checker->isBlocked('foo@example.com');
        $checker->isBlocked('bar@example.com');
        $checker->isBlocked('foo@example.com');
    }

    public function testEmptyStringShortCircuitsToFalseWithoutLoad(): void
    {
        $repo = $this->createMock(EmailBlocklistRepository::class);
        $repo->expects($this->never())->method('findAllOrdered');

        $checker = new Blocklist($repo, $this->createStub(EntityManagerInterface::class));

        static::assertFalse($checker->isBlocked(''));
        static::assertFalse($checker->isBlocked('   '));
    }

    public function testAddPersistsANewEntryAndBlocksItAtOnce(): void
    {
        // Arrange
        $repo = $this->createStub(EmailBlocklistRepository::class);
        $repo->method('findByEmail')->willReturn(null);
        $repo->method('findAllOrdered')->willReturn([]);
        $em = $this->createMock(EntityManagerInterface::class);
        $em
            ->expects($this->once())
            ->method('persist')
            ->with(static::callback(static fn(EmailBlocklistEntry $entry): bool => $entry->getEmail() === 'foo@example.com' && $entry->getReason() === 'spam'));
        $em->expects($this->once())->method('flush');
        $blocklist = new Blocklist($repo, $em);
        $blocklist->isBlocked('warm@example.com');

        // Act
        $blocklist->add(' Foo@Example.com ', 'spam');

        // Assert
        static::assertTrue($blocklist->isBlocked('foo@example.com'));
    }
}
