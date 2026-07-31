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
            [['id' => '0', 'labels' => ['en' => 'Greeting', 'de' => 'Gruss']]],
            $taxonomy->getCategories(),
        );
    }

    public function testAnUnsupportedAxisIsNotBuilt(): void
    {
        // Arrange + Act
        $form = $this->factory(['en'])->create(TaxonomyDefinitionsType::class, new Config(), ['with_tags' => false]);

        // Assert
        static::assertTrue($form->has('categories'));
        static::assertFalse($form->has('tags'));
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

    /** @param list<string> $codes */
    private function factory(array $codes): FormFactoryInterface
    {
        $languageService = $this->createStub(LanguageService::class);
        $languageService->method('getAdminFilteredEnabledCodes')->willReturn($codes);

        return Forms::createFormFactoryBuilder()
            ->addExtension(new PreloadedExtension([
                new TaxonomyDefinitionsType(),
                new TaxonomyDefinitionType($languageService),
            ], []))
            ->getFormFactory();
    }
}
