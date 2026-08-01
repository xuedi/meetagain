<?php declare(strict_types=1);

namespace Tests\Unit\Entity;

use App\Entity\ItemTag;
use PHPUnit\Framework\TestCase;

class ItemTagTest extends TestCase
{
    public function testAncestorsAreListedNearestParentFirst(): void
    {
        // Arrange
        $root = $this->tag('Meat');
        $middle = $this->tag('Poultry', $root);
        $leaf = $this->tag('Chicken', $middle);

        // Act
        $ancestors = $leaf->getAncestors();

        // Assert
        self::assertSame([$middle, $root], $ancestors);
        self::assertSame(3, $leaf->getDepth());
        self::assertSame(1, $root->getDepth());
    }

    public function testAParentCycleStopsTheAncestorWalk(): void
    {
        // Arrange
        $first = $this->tag('First');
        $second = $this->tag('Second', $first);
        $first->setParent($second);

        // Act
        $ancestors = $first->getAncestors();

        // Assert
        self::assertSame([$second], $ancestors);
    }

    public function testLabelFallsBackToTheSourceLocaleThenTheFirstFilledOne(): void
    {
        // Arrange
        $tag = new ItemTag();
        $tag->setLabels(['en' => 'Meat', 'de' => '', 'zh' => '肉']);

        // Act & Assert
        self::assertSame('肉', $tag->getLabel('zh', 'en'));
        self::assertSame('Meat', $tag->getLabel('de', 'en'));
        self::assertSame('Meat', $tag->getLabel(null, 'en'));
        self::assertSame('Meat', $tag->getLabel('de', 'fr'));
    }

    public function testAnEmptyVocabularyRowLabelsAsAnEmptyString(): void
    {
        // Arrange
        $tag = new ItemTag();

        // Act & Assert
        self::assertSame('', $tag->getLabel('en', 'en'));
    }

    private function tag(string $label, ?ItemTag $parent = null): ItemTag
    {
        $tag = new ItemTag();
        $tag->setItemType('dish');
        $tag->setLabels(['en' => $label]);
        $tag->setParent($parent);

        return $tag;
    }
}
