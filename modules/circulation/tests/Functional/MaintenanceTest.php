<?php declare(strict_types=1);

namespace Module\Circulation\Tests\Functional;

use App\Entity\User;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Module\Circulation\Contract\CirculationInterface;
use Module\Circulation\Contract\HandoverStatus;
use Module\Circulation\Contract\PortableHandover;
use Module\Circulation\Internal\CirculationService;
use Module\Circulation\Internal\Cron\MaintenanceTask;
use Module\Circulation\Tests\Stub\ParticipationProvider;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\Module\Members;

final class MaintenanceTest extends KernelTestCase
{
    private const string TYPE = ParticipationProvider::ITEM_TYPE;
    private const int ITEM = 7;

    private User $donor;

    private User $reader;

    protected function setUp(): void
    {
        self::bootKernel();
        $members = new Members(self::getContainer()->get(EntityManagerInterface::class));
        $this->donor = $members->member('Donor');
        $this->reader = $members->member('Reader');
    }

    public function testAHandoverNobodyConfirmsForAMonthIsClosed(): void
    {
        // Arrange
        $handover = $this->openHandover();
        self::getContainer()
            ->get(Connection::class)
            ->update(
                'mod_circulation_handover',
                ['opened_at' => new DateTimeImmutable('-31 days')],
                ['id' => $handover],
                ['opened_at' => Types::DATETIME_IMMUTABLE],
            );
        self::getContainer()->get(EntityManagerInterface::class)->clear();

        // Act
        $message = $this->runMaintenance();

        // Assert
        self::assertStringContainsString('1 handovers closed', $message);
        self::assertNotSame(HandoverStatus::Open, $this->handover($handover)?->status);
    }

    public function testTheTaskRunsAtMostOnceADay(): void
    {
        // Arrange
        $this->runMaintenance();

        // Act
        $second = self::getContainer()->get(MaintenanceTask::class)->runCronTask(new BufferedOutput());

        // Assert
        self::assertSame('throttled', $second->message);
    }

    public function testRebuildFindsNoDriftWhileTheLedgerAndTheCopiesAgree(): void
    {
        // Arrange
        $this->openHandover();

        // Act
        $tester = $this->rebuild(['--dry-run' => true]);

        // Assert
        $tester->assertCommandIsSuccessful();
        self::assertStringNotContainsString('from ledger', $tester->getDisplay());
    }

    public function testRebuildRestoresAHolderThatDriftedFromTheLedger(): void
    {
        // Arrange
        $copy = self::getContainer()->get(CirculationService::class)->donate(self::TYPE, self::ITEM, $this->donor, 'blue hardcover');
        self::getContainer()->get(Connection::class)->update('mod_circulation_copy', ['holder_id' => $this->reader->getId()], ['id' => $copy->getId()]);
        self::getContainer()->get(EntityManagerInterface::class)->clear();

        // Act
        $tester = $this->rebuild([]);

        // Assert
        self::assertStringContainsString('Rebuilt 1 copies from the ledger.', $tester->getDisplay());
        self::assertSame($this->donor->getId(), $this->holderOf((int) $copy->getId()));
    }

    private function openHandover(): int
    {
        $circulation = self::getContainer()->get(CirculationService::class);
        $circulation->donate(self::TYPE, self::ITEM, $this->donor, 'blue hardcover');
        $circulation->request(self::TYPE, self::ITEM, $this->reader);

        $handovers = $this->shelfHandovers();
        self::assertCount(1, $handovers);

        return $handovers[0]->ref;
    }

    private function runMaintenance(): string
    {
        return self::getContainer()->get(MaintenanceTask::class)->runCronTask(new BufferedOutput())->message;
    }

    /**
     * @param array<string, mixed> $input
     */
    private function rebuild(array $input): CommandTester
    {
        $tester = new CommandTester(new Application(self::$kernel)->find('app:circulation:rebuild'));
        $tester->execute($input);

        return $tester;
    }

    private function handover(int $ref): ?PortableHandover
    {
        return array_find($this->shelfHandovers(), static fn(PortableHandover $handover): bool => $handover->ref === $ref);
    }

    /**
     * @return list<PortableHandover>
     */
    private function shelfHandovers(): array
    {
        $circulation = self::getContainer()->get(CirculationInterface::class);

        return $circulation->export([$circulation->contextFor(self::TYPE)])->handovers;
    }

    private function holderOf(int $copyId): ?int
    {
        $holder = self::getContainer()->get(Connection::class)->fetchOne('SELECT holder_id FROM mod_circulation_copy WHERE id = ?', [$copyId]);

        return $holder === false || $holder === null ? null : (int) $holder;
    }
}
