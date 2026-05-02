<?php declare(strict_types=1);

namespace Tests\Unit\Service\Email;

use App\Entity\EmailBlocklistEntry;
use App\Repository\EmailBlocklistRepository;
use App\Service\Email\EmailBlocklistChecker;
use PHPUnit\Framework\TestCase;

final class EmailBlocklistCheckerTest extends TestCase
{
    public function testIsBlockedReturnsTrueWhenAddressInLoadedSet(): void
    {
        $entry = new EmailBlocklistEntry()->setEmail('foo@example.com');
        $repo = $this->createMock(EmailBlocklistRepository::class);
        $repo->expects($this->once())->method('findAllOrdered')->willReturn([$entry]);

        $checker = new EmailBlocklistChecker($repo);

        static::assertTrue($checker->isBlocked('foo@example.com'));
    }

    public function testCachePreventsSecondLoad(): void
    {
        $repo = $this->createMock(EmailBlocklistRepository::class);
        $repo->expects($this->once())->method('findAllOrdered')->willReturn([]);

        $checker = new EmailBlocklistChecker($repo);

        $checker->isBlocked('foo@example.com');
        $checker->isBlocked('bar@example.com');
        $checker->isBlocked('foo@example.com');
    }

    public function testNormalizationHitsSameSlot(): void
    {
        $entry = new EmailBlocklistEntry()->setEmail('Foo@Example.COM');
        $repo = $this->createMock(EmailBlocklistRepository::class);
        $repo->expects($this->once())->method('findAllOrdered')->willReturn([$entry]);

        $checker = new EmailBlocklistChecker($repo);

        static::assertTrue($checker->isBlocked('foo@example.com'));
        static::assertTrue($checker->isBlocked('Foo@Example.COM'));
        static::assertTrue($checker->isBlocked('  FOO@EXAMPLE.COM  '));
    }

    public function testEmptyStringShortCircuitsToFalseWithoutLoad(): void
    {
        $repo = $this->createMock(EmailBlocklistRepository::class);
        $repo->expects($this->never())->method('findAllOrdered');

        $checker = new EmailBlocklistChecker($repo);

        static::assertFalse($checker->isBlocked(''));
        static::assertFalse($checker->isBlocked('   '));
    }
}
