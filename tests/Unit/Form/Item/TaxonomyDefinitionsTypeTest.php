<?php declare(strict_types=1);

namespace Tests\Unit\Form\Item;

use App\Form\Item\TaxonomyDefinitionsType;
use App\Form\Item\TaxonomyDefinitionType;
use App\Item\Taxonomy\Config;
use App\Service\Config\LanguageService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\PreloadedExtension;

class TaxonomyDefinitionsTypeTest extends TestCase
{
    public function testPrefillsCategoryLabelsPerLocale(): void
    {
        // Arrange
        $taxonomy = (new Config())
            ->setCategoriesEnabled(true)
            ->setCategories([['id' => 0, 'labels' => ['en' => 'Greeting', 'de' => 'Gruss']]]);

        // Act
        $form = $this->factory(['en', 'de'])->create(TaxonomyDefinitionsType::class, $taxonomy);

        // Assert
        $row = $form->get('categories')->get('0');
        static::assertSame('Greeting', $row->get('en')->getData());
        static::assertSame('Gruss', $row->get('de')->getData());
    }

    public function testSubmitRoundTripsLabelsIntoTaxonomy(): void
    {
        // Arrange
        $form = $this->factory(['en', 'de'])->create(TaxonomyDefinitionsType::class, new Config());

        // Act
        $form->submit([
            'categories' => [
                ['id' => '0', 'en' => 'Greeting', 'de' => 'Gruss'],
            ],
            'tags' => [],
        ]);

        // Assert
        $taxonomy = $form->getData();
        static::assertInstanceOf(Config::class, $taxonomy);
        static::assertSame(
            [['id' => '0', 'labels' => ['en' => 'Greeting', 'de' => 'Gruss'], 'group' => null]],
            $taxonomy->getCategories(),
        );
    }

    public function testAnUnsupportedAxisIsNotBuilt(): void
    {
        // Arrange + Act
        $form = $this->factory(['en'])->create(TaxonomyDefinitionsType::class, new Config(), ['with_tags' => false]);

        // Assert
        static::assertTrue($form->has('categories'));
        static::assertTrue($form->has('categoryGroups'));
        static::assertFalse($form->has('tags'));
        static::assertFalse($form->has('tagGroups'));
    }

    public function testTheGroupSelectOffersTheSavedGroupsAndAGroupRowHasNone(): void
    {
        // Arrange
        $taxonomy = (new Config())->setCategoryGroups([['id' => 4, 'labels' => ['en' => 'Meat', 'de' => 'Fleisch']]]);
        $taxonomy->setCategories([['id' => 0, 'labels' => ['en' => 'Chicken']]]);

        // Act
        $form = $this->factory(['en', 'de'])->create(TaxonomyDefinitionsType::class, $taxonomy);

        // Assert
        static::assertSame(
            ['Meat' => 4],
            $form->get('categories')->get('0')->get('group')->getConfig()->getOption('choices'),
        );
        static::assertFalse($form->get('categoryGroups')->get('0')->has('group'));
    }

    public function testSubmitFilesADefinitionUnderAGroup(): void
    {
        // Arrange
        $taxonomy = (new Config())->setCategoryGroups([['id' => 4, 'labels' => ['en' => 'Meat']]]);
        $form = $this->factory(['en'])->create(TaxonomyDefinitionsType::class, $taxonomy);

        // Act
        $form->submit([
            'categoryGroups' => [['id' => '4', 'en' => 'Meat']],
            'categories' => [['id' => '', 'group' => '4', 'en' => 'Chicken']],
            'tagGroups' => [],
            'tags' => [],
        ]);
        $taxonomy->normalize();

        // Assert
        static::assertSame(4, $taxonomy->categoryDefinitions()[0]->group);
        static::assertSame('Meat', $taxonomy->categoryGroupDefinitions()[0]->labelFor('en', 'en'));
    }

    public function testUsageCountsReachTheRowView(): void
    {
        // Arrange
        $taxonomy = (new Config())->setCategories([['id' => 3, 'labels' => ['en' => 'Greeting']]]);

        // Act
        $view = $this->factory(['en'])
            ->create(TaxonomyDefinitionsType::class, $taxonomy, ['usage' => ['category' => [3 => 7]]])
            ->createView();

        // Assert
        static::assertSame([3 => 7], $view['categories']->children['0']->vars['usage']);
    }

    public function testADefinitionCanBeFiledUnderAGroupAddedInTheSameSubmit(): void
    {
        // Arrange
        $taxonomy = new Config();
        $form = $this->factory(['en'])->create(TaxonomyDefinitionsType::class, $taxonomy);

        // Act
        $form->submit([
            'categoryGroups' => [['id' => 'n1', 'en' => 'Meat']],
            'categories' => [['id' => '', 'group' => 'n1', 'en' => 'Chicken']],
            'tagGroups' => [],
            'tags' => [],
        ]);
        $taxonomy->normalize();

        // Assert
        static::assertTrue($form->isValid());
        static::assertSame(0, $taxonomy->categoryGroupDefinitions()[0]->id);
        static::assertSame(0, $taxonomy->categoryDefinitions()[0]->group);
    }

    public function testAGroupValueMatchingNoSubmittedGroupIsRejected(): void
    {
        // Arrange
        $form = $this->factory(['en'])->create(TaxonomyDefinitionsType::class, new Config());

        // Act
        $form->submit([
            'categoryGroups' => [],
            'categories' => [['id' => '', 'group' => 'n9', 'en' => 'Chicken']],
            'tagGroups' => [],
            'tags' => [],
        ]);

        // Assert
        static::assertFalse($form->isValid());
    }

    /** @param list<string> $codes */
    private function factory(array $codes): FormFactoryInterface
    {
        $languageService = $this->createStub(LanguageService::class);
        $languageService->method('getAdminFilteredEnabledCodes')->willReturn($codes);
        $languageService->method('getFilteredDefaultLocale')->willReturn($codes[0]);

        return Forms::createFormFactoryBuilder()
            ->addExtension(new PreloadedExtension([
                new TaxonomyDefinitionsType($languageService),
                new TaxonomyDefinitionType($languageService),
            ], []))
            ->getFormFactory();
    }
}
