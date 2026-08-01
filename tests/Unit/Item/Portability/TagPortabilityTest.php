<?php declare(strict_types=1);

namespace Tests\Unit\Item\Portability;

use App\Item\Portability\TagPortability;
use App\Item\Tag\TagService;
use App\Repository\ItemTagAssignmentRepository;
use PHPUnit\Framework\TestCase;

class TagPortabilityTest extends TestCase
{
    public function testExportReadsTheAssignmentTableKeyedBySourceItemId(): void
    {
        // Arrange
        $tagRepo = $this->createStub(ItemTagAssignmentRepository::class);
        $tagRepo->method('tagIdsForItems')->willReturn([12 => [2, 5]]);

        $portability = new TagPortability($tagRepo, $this->createStub(TagService::class));

        // Act
        $block = $portability->export('dish', [12]);

        // Assert
        self::assertSame(['tags' => [12 => [2, 5]]], $block);
    }

    public function testImportRekeysThroughTheRefMap(): void
    {
        // Arrange
        $writes = [];
        $service = $this->createStub(TagService::class);
        $service->method('getChoices')->willReturn([2 => 'Meat', 5 => 'Spicy']);
        $service->method('getTagIds')->willReturn([]);
        $service->method('setTags')->willReturnCallback(static function (string $type, int $id, array $tags) use (&$writes): void {
            $writes[] = [$type, $id, $tags];
        });

        $portability = new TagPortability($this->createStub(ItemTagAssignmentRepository::class), $service);

        // Act
        $dropped = $portability->import('dish', ['tags' => [12 => [2, 5]]], [12 => 91]);

        // Assert
        self::assertSame(0, $dropped);
        self::assertSame([['dish', 91, [2, 5]]], $writes);
    }

    public function testUndefinedTagIdsAreDroppedAndCounted(): void
    {
        // Arrange
        $service = $this->createStub(TagService::class);
        $service->method('getChoices')->willReturn([2 => 'Meat']);
        $service->method('getTagIds')->willReturn([]);

        $portability = new TagPortability($this->createStub(ItemTagAssignmentRepository::class), $service);

        // Act
        $dropped = $portability->import('dish', ['tags' => [12 => [2, 98]]], [12 => 91]);

        // Assert
        self::assertSame(1, $dropped);
    }

    public function testAssignmentOfARefMissingFromTheMapIsDropped(): void
    {
        // Arrange
        $service = $this->createStub(TagService::class);
        $service->method('getChoices')->willReturn([2 => 'Meat']);
        $service->method('getTagIds')->willReturn([]);

        $portability = new TagPortability($this->createStub(ItemTagAssignmentRepository::class), $service);

        // Act
        $dropped = $portability->import('dish', ['tags' => [77 => [2]]], [12 => 91]);

        // Assert
        self::assertSame(1, $dropped);
    }

    public function testIncomingTagsMergeWithTheOnesTheTargetAlreadyCarries(): void
    {
        // Arrange
        $mergedTags = null;
        $service = $this->createStub(TagService::class);
        $service->method('getChoices')->willReturn([2 => 'Meat']);
        $service->method('getTagIds')->willReturn([9]);
        $service->method('setTags')->willReturnCallback(static function (string $type, int $id, array $tags) use (&$mergedTags): void {
            $mergedTags = $tags;
        });

        $portability = new TagPortability($this->createStub(ItemTagAssignmentRepository::class), $service);

        // Act
        $portability->import('dish', ['tags' => [12 => [2]]], [12 => 91]);

        // Assert
        self::assertSame([9, 2], $mergedTags);
    }
}
