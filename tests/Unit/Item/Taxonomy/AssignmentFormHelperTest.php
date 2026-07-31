<?php declare(strict_types=1);

namespace Tests\Unit\Item\Taxonomy;

use App\Item\Taxonomy\AssignmentFormHelper;
use App\Item\Taxonomy\CategorizableTypeProviderInterface;
use App\Item\Taxonomy\CategorizableTypeRegistry;
use App\Item\Taxonomy\Config;
use App\Item\Taxonomy\TaxonomyService;
use App\Service\Config\LanguageService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class AssignmentFormHelperTest extends TestCase
{
    public function testTheCategorySelectNestsGroupedEntriesUnderTheirGroupLabel(): void
    {
        // Arrange
        $builder = $this->builder($this->groupedTaxonomy());

        // Act
        $choices = $builder->get(AssignmentFormHelper::CATEGORY_FIELD)->getOption('choices');

        // Assert
        static::assertSame(['Soup' => 6, 'Meat' => ['Chicken' => 5, 'Beef' => 7]], $choices);
    }

    public function testTheTagCheckboxesCarryTheirGroupingAndTheirOwnWidgetBlock(): void
    {
        // Arrange
        $builder = $this->builder($this->groupedTaxonomy());

        // Act
        $tags = $builder->get(AssignmentFormHelper::TAGS_FIELD);

        // Assert
        static::assertSame(['Sweet' => 2, 'Heat' => ['Spicy' => 1]], $tags->getOption('choices'));
        static::assertSame('item_taxonomy_tags', $tags->getOption('block_prefix'));
        static::assertTrue($tags->getOption('expanded'));
    }

    public function testAVocabularyWithoutGroupsStaysFlat(): void
    {
        // Arrange
        $taxonomy = (new Config())
            ->setCategoriesEnabled(true)
            ->setTagsEnabled(true)
            ->setCategories([['id' => 3, 'labels' => ['en' => 'Slang']]])
            ->setTags([['id' => 4, 'labels' => ['en' => 'Formal']]]);
        $builder = $this->builder($taxonomy);

        // Act + Assert
        static::assertSame(['Slang' => 3], $builder->get(AssignmentFormHelper::CATEGORY_FIELD)->getOption('choices'));
        static::assertSame(['Formal' => 4], $builder->get(AssignmentFormHelper::TAGS_FIELD)->getOption('choices'));
    }

    public function testAnUnknownTypeAddsNoFields(): void
    {
        // Arrange
        $registry = $this->createStub(CategorizableTypeRegistry::class);
        $registry->method('providerFor')->willReturn(null);
        $builder = Forms::createFormFactoryBuilder()->getFormFactory()->createBuilder();

        // Act
        $this->helper($registry)->addAssignmentFields($builder, 'nonesuch', null);

        // Assert
        static::assertFalse($builder->has(AssignmentFormHelper::CATEGORY_FIELD));
        static::assertFalse($builder->has(AssignmentFormHelper::TAGS_FIELD));
    }

    private function groupedTaxonomy(): Config
    {
        return (new Config())
            ->setCategoriesEnabled(true)
            ->setTagsEnabled(true)
            ->setCategoryGroups([['id' => 0, 'labels' => ['en' => 'Meat']]])
            ->setCategories([
                ['id' => 5, 'labels' => ['en' => 'Chicken'], 'group' => 0],
                ['id' => 6, 'labels' => ['en' => 'Soup']],
                ['id' => 7, 'labels' => ['en' => 'Beef'], 'group' => 0],
            ])
            ->setTagGroups([['id' => 0, 'labels' => ['en' => 'Heat']]])
            ->setTags([
                ['id' => 1, 'labels' => ['en' => 'Spicy'], 'group' => 0],
                ['id' => 2, 'labels' => ['en' => 'Sweet']],
            ]);
    }

    private function builder(Config $taxonomy): FormBuilderInterface
    {
        $provider = $this->createStub(CategorizableTypeProviderInterface::class);
        $provider->method('getTaxonomy')->willReturn($taxonomy);
        $provider->method('supportsCategories')->willReturn(true);
        $provider->method('supportsTags')->willReturn(true);

        $registry = $this->createStub(CategorizableTypeRegistry::class);
        $registry->method('providerFor')->willReturn($provider);

        $builder = Forms::createFormFactoryBuilder()->getFormFactory()->createBuilder();
        $this->helper($registry)->addAssignmentFields($builder, 'dish', null);

        return $builder;
    }

    private function helper(CategorizableTypeRegistry $registry): AssignmentFormHelper
    {
        $languageService = $this->createStub(LanguageService::class);
        $languageService->method('getFilteredDefaultLocale')->willReturn('en');

        $requestStack = new RequestStack();
        $requestStack->push(new Request());

        return new AssignmentFormHelper(
            $registry,
            $this->createStub(TaxonomyService::class),
            $languageService,
            $requestStack,
        );
    }
}
