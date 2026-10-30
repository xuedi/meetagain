<?php declare(strict_types=1);

namespace Module\Proposal\Tests\Functional;

use Module\Proposal\Contract\Change\ChangeInterface;
use Module\Proposal\Contract\Suggestion\SuggestionInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ContainerTest extends KernelTestCase
{
    public function testTheContractResolvesWithoutAnyPlugin(): void
    {
        // Arrange
        self::bootKernel();

        // Act
        $service = self::getContainer()->get(SuggestionInterface::class);

        // Assert
        self::assertInstanceOf(SuggestionInterface::class, $service);
    }

    public function testTheChangeContractResolvesWithoutAnyPlugin(): void
    {
        // Arrange
        self::bootKernel();

        // Act
        $service = self::getContainer()->get(ChangeInterface::class);

        // Assert
        self::assertInstanceOf(ChangeInterface::class, $service);
    }
}
