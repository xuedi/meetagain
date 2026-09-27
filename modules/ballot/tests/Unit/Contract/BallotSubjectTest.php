<?php declare(strict_types=1);

namespace Module\Ballot\Tests\Unit\Contract;

use Module\Ballot\Contract\BallotSubject;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class BallotSubjectTest extends TestCase
{
    #[DataProvider('provideComparisons')]
    public function testTwoSubjectsAreEqualOnlyWhenTypeAndIdBothMatch(?BallotSubject $other, bool $expected): void
    {
        // Arrange
        $subject = new BallotSubject('event', 7);

        // Act
        $equal = $subject->equals($other);

        // Assert
        static::assertSame($expected, $equal);
    }

    /**
     * @return iterable<string, array{?BallotSubject, bool}>
     */
    public static function provideComparisons(): iterable
    {
        yield 'the same type and id' => [new BallotSubject('event', 7), true];
        yield 'another id' => [new BallotSubject('event', 8), false];
        yield 'another type' => [new BallotSubject('item', 7), false];
        yield 'no subject' => [null, false];
    }
}
