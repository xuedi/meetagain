<?php declare(strict_types=1);

namespace Module\Task\Tests\Functional;

use DateTimeImmutable;
use Module\Task\Contract\Status;
use Module\Task\Internal\Entity\Run;
use Module\Task\Internal\Repository\RunRepository;
use Module\Task\Internal\RunRetention;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;

final class RetentionTest extends KernelTestCase
{
    private const string IDENTIFIER = 'retention-probe';

    public function testRunsOlderThanThirtyDaysAreDeletedAndNewerOnesKept(): void
    {
        // Arrange
        self::bootKernel();
        $runs = self::getContainer()->get(RunRepository::class);
        $now = new DateTimeImmutable('2026-10-18 03:15:00');
        $runs->record(new Run(self::IDENTIFIER, $now->modify('-31 days'), 10, Status::ok, 'old', 'host'));
        $runs->record(new Run(self::IDENTIFIER, $now->modify('-29 days'), 10, Status::ok, 'recent', 'host'));
        $retention = new RunRetention($runs, new MockClock($now));

        // Act
        $result = $retention->run();

        // Assert
        self::assertSame('1 runs deleted', $result->message);
        $left = array_map(static fn(Run $run): string => $run->getMessage(), $runs->findBy(['identifier' => self::IDENTIFIER]));
        self::assertSame(['recent'], $left);
    }
}
