<?php declare(strict_types=1);

namespace Tests\Unit\Anonymization;

use App\Anonymization\AnonymizerInterface;
use App\Anonymization\Context;
use App\Anonymization\Scrubber;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

abstract class AnonymizerTestCase extends TestCase
{
    protected const string HASH = 'argon-hash-of-1234';

    /** @var list<array{string, array<string, mixed>}> */
    protected array $statements = [];

    protected function setUp(): void
    {
        $this->statements = [];
    }

    protected function scrubber(): Scrubber
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('quoteSingleIdentifier')->willReturnCallback(static fn(string $identifier): string => '`' . $identifier . '`');
        $connection
            ->method('executeStatement')
            ->willReturnCallback(function (string $sql, array $params = []): int {
                $this->statements[] = [$sql, $params];

                return 1;
            });

        return new Scrubber($connection);
    }

    protected function context(): Context
    {
        return new Context(self::HASH, static function (string $line): void {});
    }

    /**
     * @param list<string> $viaContract tables whose rows go through a module contract instead of the scrubber
     */
    protected function assertEveryClaimIsActedOn(AnonymizerInterface $anonymizer, array $viaContract = []): void
    {
        foreach ($anonymizer->getClaims() as $claim) {
            if (in_array($claim->table, $viaContract, true)) {
                continue;
            }
            if ($claim->emptied) {
                self::assertContains(sprintf('DELETE FROM `%s`', $claim->table), array_column($this->statements, 0));
            }
            foreach ([...$claim->rewritten, ...$claim->cleared] as $column) {
                self::assertTrue(
                    array_any(
                        $this->statements,
                        static fn(array $statement): bool => (
                            str_starts_with($statement[0], sprintf('UPDATE `%s` SET ', $claim->table))
                            && str_contains($statement[0], sprintf('`%s` =', $column))
                        ),
                    ),
                    sprintf('No statement writes %s.%s.', $claim->table, $column),
                );
            }
        }
    }

    /**
     * @return list<array{string, array<string, mixed>}>
     */
    protected function statementsOn(string $table): array
    {
        return array_values(array_filter($this->statements, static fn(array $statement): bool => str_contains($statement[0], sprintf('`%s`', $table))));
    }
}
