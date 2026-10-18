<?php declare(strict_types=1);

namespace Module\Task\Tests\Unit\Internal;

use LogicException;
use Module\Task\Internal\Registry;
use Module\Task\Tests\Stub\Task;
use PHPUnit\Framework\TestCase;

final class RegistryTest extends TestCase
{
    public function testAllIsKeyedAndSortedByIdentifier(): void
    {
        // Arrange
        $registry = new Registry([new Task('zeta'), new Task('alpha')]);

        // Act
        $all = $registry->all();

        // Assert
        self::assertSame(['alpha', 'zeta'], array_keys($all));
    }

    public function testFindReturnsNullForAnUnknownIdentifier(): void
    {
        // Arrange
        $registry = new Registry([new Task('alpha')]);

        // Act
        $found = $registry->find('beta');

        // Assert
        self::assertNull($found);
    }

    public function testTwoTasksWithOneIdentifierAreRefused(): void
    {
        // Arrange
        $registry = new Registry([new Task('alpha'), new Task('alpha')]);

        // Assert
        $this->expectException(LogicException::class);

        // Act
        $registry->all();
    }
}
