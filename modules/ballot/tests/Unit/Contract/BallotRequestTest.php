<?php declare(strict_types=1);

namespace Module\Ballot\Tests\Unit\Contract;

use DateTimeImmutable;
use Module\Ballot\Contract\BallotRequest;
use Module\Ballot\Contract\Candidate;
use PHPUnit\Framework\TestCase;

final class BallotRequestTest extends TestCase
{
    public function testCandidateKeysKeepTheirOrderAndDropDuplicates(): void
    {
        // Arrange
        $request = new BallotRequest(
            'test.purpose',
            [new Candidate('b', 'B'), new Candidate('a', 'A'), new Candidate('b', 'B again')],
            new DateTimeImmutable('2031-01-01'),
            1,
        );

        // Act
        $keys = $request->candidateKeys();

        // Assert
        static::assertSame(['b', 'a'], $keys);
    }
}
