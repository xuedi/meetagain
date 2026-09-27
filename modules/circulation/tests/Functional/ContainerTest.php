<?php declare(strict_types=1);

namespace Module\Circulation\Tests\Functional;

use Module\Circulation\Contract\CirculationInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ContainerTest extends KernelTestCase
{
    /**
     * @param class-string $contract
     */
    #[DataProvider('provideContracts')]
    public function testTheContractResolvesWithoutAnyPlugin(string $contract): void
    {
        // Arrange
        self::bootKernel();

        // Act
        $service = self::getContainer()->get($contract);

        // Assert
        self::assertInstanceOf($contract, $service);
    }

    public function testNoPluginServiceIsRegistered(): void
    {
        // Arrange
        self::bootKernel();

        // Act
        $pluginServices = array_filter(
            self::getContainer()->getServiceIds(),
            static fn(string $id): bool => str_starts_with($id, 'Plugin\\') && preg_match('~^Plugin\\\\\w+\\\\Module\\\\~', $id) !== 1,
        );

        // Assert
        self::assertSame([], array_values($pluginServices));
    }

    /**
     * @return iterable<string, array{class-string}>
     */
    public static function provideContracts(): iterable
    {
        yield 'CirculationInterface' => [CirculationInterface::class];
    }
}
