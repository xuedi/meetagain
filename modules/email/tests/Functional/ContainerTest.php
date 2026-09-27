<?php declare(strict_types=1);

namespace Module\Email\Tests\Functional;

use Module\Email\Contract\BlocklistInterface;
use Module\Email\Contract\MailerInterface;
use Module\Email\Contract\PreviewSweepInterface;
use Module\Email\Contract\SendlogInterface;
use Module\Email\Contract\TemplatesInterface;
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
        yield 'MailerInterface' => [MailerInterface::class];
        yield 'TemplatesInterface' => [TemplatesInterface::class];
        yield 'BlocklistInterface' => [BlocklistInterface::class];
        yield 'SendlogInterface' => [SendlogInterface::class];
        yield 'PreviewSweepInterface' => [PreviewSweepInterface::class];
    }
}
