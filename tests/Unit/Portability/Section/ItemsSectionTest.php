<?php declare(strict_types=1);

namespace Tests\Unit\Portability\Section;

use App\Portability\Item\ContributorInterface;
use App\Portability\Item\ImportResult;
use App\Portability\Item\Registry;
use App\Portability\Item\TagAssignments;
use App\Portability\Outcome;
use App\Portability\Scope;
use App\Portability\Section\ItemsSection;

final class ItemsSectionTest extends SectionTestCase
{
    public function testEachTypeCarriesItsRowsPlusItsTagAssignments(): void
    {
        // Arrange
        $tags = $this->createStub(TagAssignments::class);
        $tags->method('export')->willReturn(['tags' => [12 => [5]]]);
        $section = new ItemsSection($this->registry([$this->contributor('dish', [['ref' => 12, 'origin' => 'Sicily']])]), $tags);

        // Act
        $items = $section->export(new Scope(itemIds: ['dish' => [12]]), $this->images());

        // Assert
        static::assertSame(['dish' => ['rows' => [['ref' => 12, 'origin' => 'Sicily']], 'tags' => [12 => [5]]]], $items);
    }

    public function testRowsAndTypesAreWrittenInAStableOrder(): void
    {
        // Arrange
        $section = new ItemsSection($this->registry([
            $this->contributor('glossary', [['ref' => 9], ['ref' => 2]]),
            $this->contributor('book', [['ref' => 4]]),
        ]), $this->createStub(TagAssignments::class));

        // Act
        $items = $section->export(new Scope(itemIds: ['glossary' => [9, 2], 'book' => [4]]), $this->images());

        // Assert
        static::assertSame(['book', 'glossary'], array_keys($items));
        static::assertSame([['ref' => 2], ['ref' => 9]], $items['glossary']['rows']);
    }

    public function testATypeTheScopeDoesNotNameIsOmitted(): void
    {
        // Arrange
        $section = new ItemsSection($this->registry([$this->contributor('dish', [['ref' => 12]])]), $this->createStub(TagAssignments::class));

        // Act
        $items = $section->export(new Scope(itemIds: ['dish' => []]), $this->images());

        // Assert
        static::assertSame([], $items);
    }

    public function testATypeThatExportsNoRowsIsOmitted(): void
    {
        // Arrange
        $section = new ItemsSection($this->registry([$this->contributor('dish', [])]), $this->createStub(TagAssignments::class));

        // Act
        $items = $section->export(new Scope(itemIds: ['dish' => [12]]), $this->images());

        // Assert
        static::assertSame([], $items);
    }

    public function testTheExportOffersOnlyTheScopesTagsOfTheType(): void
    {
        // Arrange
        $allowed = null;
        $tags = $this->createStub(TagAssignments::class);
        $tags->method('export')->willReturnCallback(static function (string $type, array $itemIds, array $tagIds) use (&$allowed): array {
            $allowed = $tagIds;

            return ['tags' => []];
        });
        $section = new ItemsSection($this->registry([$this->contributor('dish', [['ref' => 12]])]), $tags);

        // Act
        $section->export(new Scope(itemIds: ['dish' => [12]], tagIds: ['dish' => [2, 5], 'book' => [9]]), $this->images());

        // Assert
        static::assertSame([2, 5], $allowed);
    }

    public function testEachTypeReportsItsCountsMapsItsItemsAndRekeysItsTags(): void
    {
        // Arrange
        $seenMap = null;
        $tags = $this->createStub(TagAssignments::class);
        $tags->method('import')->willReturnCallback(static function (string $type, array $block, array $map) use (&$seenMap): void {
            $seenMap = $map;
        });
        $contributor = $this->contributor('dish', [], new ImportResult([7 => 91], created: 1, matched: 2));
        $context = $this->context();

        // Act
        new ItemsSection($this->registry([$contributor]), $tags)->import(['dish' => ['rows' => [['ref' => 7]]]], $context);

        // Assert
        $summary = $context->toSummary();
        static::assertSame(1, $summary->get('dish', Outcome::Created));
        static::assertSame(2, $summary->get('dish', Outcome::Matched));
        static::assertSame([7 => 91], $seenMap);
        static::assertSame(91, $context->resolveItem('dish', 7));
    }

    public function testEveryRowOfATypeWithoutAnActiveContributorIsSkipped(): void
    {
        // Arrange
        $context = $this->context();

        // Act
        new ItemsSection($this->registry([]), $this->createStub(TagAssignments::class))->import([
            'karaoke' => ['rows' => [['ref' => 1], ['ref' => 2]]],
        ], $context);

        // Assert
        static::assertSame(['karaoke' => ['skipped' => 2]], $context->toSummary()->counts);
        static::assertFalse($context->knowsItemType('karaoke'));
    }

    /**
     * @param list<ContributorInterface> $contributors
     */
    private function registry(array $contributors): Registry
    {
        $registry = $this->createStub(Registry::class);
        $registry->method('all')->willReturn($contributors);
        $registry
            ->method('contributorFor')
            ->willReturnCallback(static fn(string $itemType): ?ContributorInterface => array_find(
                $contributors,
                static fn(ContributorInterface $contributor): bool => $contributor->getItemType() === $itemType,
            ));

        return $registry;
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    private function contributor(string $itemType, array $rows, ?ImportResult $result = null): ContributorInterface
    {
        $contributor = $this->createStub(ContributorInterface::class);
        $contributor->method('getItemType')->willReturn($itemType);
        $contributor->method('exportItems')->willReturn($rows);
        $contributor->method('importItems')->willReturn($result ?? new ImportResult([], 0, 0));

        return $contributor;
    }
}
