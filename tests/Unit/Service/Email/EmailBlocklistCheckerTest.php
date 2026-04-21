<?php declare(strict_types=1);

namespace Tests\Unit\Service\Email;

use App\Repository\EmailBlocklistRepository;
use App\Service\Email\EmailBlocklistChecker;
use PHPUnit\Framework\TestCase;

final class EmailBlocklistCheckerTest extends TestCase
{
    public function testIsBlockedDelegatesToRepository(): void
    {
        $repo = $this->createMock(EmailBlocklistRepository::class);
        $repo->expects($this->once())->method('isBlocked')->with('foo@example.com')->willReturn(true);

        $checker = new EmailBlocklistChecker($repo);

        static::assertTrue($checker->isBlocked('foo@example.com'));
    }

    public function testMemoPreventsSecondRepoCall(): void
    {
        $repo = $this->createMock(EmailBlocklistRepository::class);
        $repo->expects($this->once())->method('isBlocked')->willReturn(false);

        $checker = new EmailBlocklistChecker($repo);

        $checker->isBlocked('foo@example.com');
        $checker->isBlocked('foo@example.com');
        $checker->isBlocked('foo@example.com');
    }

    public function testNormalizationHitsSameMemoSlot(): void
    {
        // Arrange: stub the repo to count calls
        $repo = $this->createMock(EmailBlocklistRepository::class);
        $repo->expects($this->once())->method('isBlocked')->willReturn(true);

        $checker = new EmailBlocklistChecker($repo);

        // Act: mixed case, trimmed and untrimmed
        static::assertTrue($checker->isBlocked('Foo@Example.COM'));
        static::assertTrue($checker->isBlocked('foo@example.com'));
        static::assertTrue($checker->isBlocked('  FOO@EXAMPLE.COM  '));
    }

    public function testEmptyStringShortCircuitsToFalseWithoutRepoCall(): void
    {
        $repo = $this->createMock(EmailBlocklistRepository::class);
        $repo->expects($this->never())->method('isBlocked');

        $checker = new EmailBlocklistChecker($repo);

        static::assertFalse($checker->isBlocked(''));
        static::assertFalse($checker->isBlocked('   '));
    }
}
