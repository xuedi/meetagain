<?php declare(strict_types=1);

namespace Module\Suggestion\Tests\Functional;

use Module\Suggestion\Contract\SuggestionInterface;
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
}
