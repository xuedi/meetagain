<?php declare(strict_types=1);

namespace Tests\Unit\DataHotfix;

use App\DataHotfix\Hotfixes\RestoreItemTagClosure;
use App\Item\Tag\AssignmentClosure;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class RestoreItemTagClosureTest extends TestCase
{
    public function testEveryTypeWithTagsHasItsClosureRestored(): void
    {
        // Arrange
        $connection = $this->createStub(Connection::class);
        $connection->method('fetchFirstColumn')->willReturn(['dish', 'glossary']);
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getConnection')->willReturn($connection);
        $restored = [];
        $closure = $this->createMock(AssignmentClosure::class);
        $closure
            ->expects(static::exactly(2))
            ->method('restore')
            ->willReturnCallback(static function (string $itemType) use (&$restored): void {
                $restored[] = $itemType;
            });

        // Act
        new RestoreItemTagClosure($em, $closure)->execute();

        // Assert
        static::assertSame(['dish', 'glossary'], $restored);
    }
}
