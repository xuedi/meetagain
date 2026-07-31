<?php declare(strict_types=1);

namespace Tests\Unit\Form\Item;

use App\Form\Item\TaxonomyConfigType;
use App\Item\Taxonomy\Config;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\PreloadedExtension;

class TaxonomyConfigTypeTest extends TestCase
{
    public function testCarriesTheEnableTogglesOnly(): void
    {
        // Arrange + Act
        $form = $this->factory()->create(TaxonomyConfigType::class, new Config());

        // Assert
        static::assertTrue($form->has('categoriesEnabled'));
        static::assertTrue($form->has('tagsEnabled'));
        static::assertFalse($form->has('categories'), 'Definitions moved to the central taxonomy page');
        static::assertFalse($form->has('tags'));
    }

    public function testSubmitLeavesTheDefinitionsUntouched(): void
    {
        // Arrange
        $taxonomy = (new Config())
            ->setCategories([['id' => 0, 'labels' => ['en' => 'Greeting']]])
            ->setTags([['id' => 0, 'labels' => ['en' => 'Formal']]]);
        $form = $this->factory()->create(TaxonomyConfigType::class, $taxonomy);

        // Act
        $form->submit(['categoriesEnabled' => '1', 'tagsEnabled' => null]);

        // Assert
        $submitted = $form->getData();
        static::assertInstanceOf(Config::class, $submitted);
        static::assertTrue($submitted->isCategoriesEnabled());
        static::assertFalse($submitted->isTagsEnabled());
        static::assertSame([['id' => 0, 'labels' => ['en' => 'Greeting']]], $submitted->getCategories());
        static::assertSame([['id' => 0, 'labels' => ['en' => 'Formal']]], $submitted->getTags());
    }

    private function factory(): FormFactoryInterface
    {
        return Forms::createFormFactoryBuilder()
            ->addExtension(new PreloadedExtension([new TaxonomyConfigType()], []))
            ->getFormFactory();
    }
}
