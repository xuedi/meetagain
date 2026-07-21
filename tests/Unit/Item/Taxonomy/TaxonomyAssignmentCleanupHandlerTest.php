<?php declare(strict_types=1);

namespace Tests\Unit\Item\Taxonomy;

use App\Enum\ItemAction;
use App\Item\Taxonomy\TaxonomyAssignmentCleanupHandler;
use App\Repository\ItemCategoryAssignmentRepository;
use App\Repository\ItemTagAssignmentRepository;
use PHPUnit\Framework\TestCase;

class TaxonomyAssignmentCleanupHandlerTest extends TestCase
{
    public function testDeletedActionSweepsBothAssignmentTables(): void
    {
        // Arrange
        $categoryRepo = $this->createMock(ItemCategoryAssignmentRepository::class);
        $categoryRepo->expects(self::once())->method('deleteFor')->with('dish', 42);
        $tagRepo = $this->createMock(ItemTagAssignmentRepository::class);
        $tagRepo->expects(self::once())->method('deleteFor')->with('dish', 42);
        $handler = new TaxonomyAssignmentCleanupHandler($categoryRepo, $tagRepo);

        // Act
        $handler->onItemAction(ItemAction::Deleted, 'dish', 42);

        // Assert - mocks verify deleteFor was called on both
    }

    public function testNonDeleteActionIsIgnored(): void
    {
        // Arrange
        $categoryRepo = $this->createMock(ItemCategoryAssignmentRepository::class);
        $categoryRepo->expects(self::never())->method('deleteFor');
        $tagRepo = $this->createMock(ItemTagAssignmentRepository::class);
        $tagRepo->expects(self::never())->method('deleteFor');
        $handler = new TaxonomyAssignmentCleanupHandler($categoryRepo, $tagRepo);

        // Act
        $handler->onItemAction(ItemAction::Updated, 'dish', 42);

        // Assert - mocks verify deleteFor was never called
    }
}
