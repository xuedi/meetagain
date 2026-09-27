<?php declare(strict_types=1);

namespace Module\Email\Tests\Functional;

use Module\Email\Contract\BlocklistInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class BlocklistTest extends KernelTestCase
{
    public function testAnAddressIsBlockedWhateverItsCaseAndSpacing(): void
    {
        // Arrange
        self::bootKernel();
        $blocklist = self::getContainer()->get(BlocklistInterface::class);

        // Act
        $blocklist->add(' Someone@Module-Test.Example ', 'bounced');

        // Assert
        self::assertTrue($blocklist->isBlocked('someone@module-test.example'));
        self::assertTrue($blocklist->isBlocked('SOMEONE@module-test.example'));
        self::assertSame('bounced', $blocklist->reasonFor('someone@module-test.example'));
    }

    public function testAddingAnAddressTwiceKeepsTheFirstReason(): void
    {
        // Arrange
        self::bootKernel();
        $blocklist = self::getContainer()->get(BlocklistInterface::class);
        $blocklist->add('someone@module-test.example', 'bounced');

        // Act
        $blocklist->add('someone@module-test.example', 'complained');

        // Assert
        self::assertSame('bounced', $blocklist->reasonFor('someone@module-test.example'));
    }

    public function testAnUnknownAddressIsNotBlockedAndHasNoReason(): void
    {
        // Arrange
        self::bootKernel();
        $blocklist = self::getContainer()->get(BlocklistInterface::class);

        // Act
        $state = [$blocklist->isBlocked('nobody@module-test.example'), $blocklist->reasonFor('nobody@module-test.example')];

        // Assert
        self::assertSame([false, null], $state);
    }
}
