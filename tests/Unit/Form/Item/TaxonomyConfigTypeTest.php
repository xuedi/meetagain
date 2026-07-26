<?php declare(strict_types=1);

namespace Tests\Unit\Form\Item;

use App\Form\Item\TaxonomyConfigType;
use App\Form\Item\TaxonomyDefinitionType;
use App\Item\Taxonomy\Config;
use App\Service\Config\LanguageService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\PreloadedExtension;

class TaxonomyConfigTypeTest extends TestCase
{
    public function testPrefillsCategoryLabelsPerLocale(): void
    {
        // Arrange
        $taxonomy = (new Config())
            ->setCategoriesEnabled(true)
            ->setCategories([['id' => 0, 'labels' => ['en' => 'Greeting', 'de' => 'Gruss']]]);

        // Act
        $form = $this->factory(['en', 'de'])->create(TaxonomyConfigType::class, $taxonomy);

        // Assert
        $row = $form->get('categories')->get('0');
        static::assertSame('Greeting', $row->get('en')->getData());
        static::assertSame('Gruss', $row->get('de')->getData());
    }

    public function testSubmitRoundTripsLabelsIntoTaxonomy(): void
    {
        // Arrange
        $form = $this->factory(['en', 'de'])->create(TaxonomyConfigType::class, new Config());

        // Act
        $form->submit([
            'categoriesEnabled' => '1',
            'categories' => [
                ['id' => '0', 'en' => 'Greeting', 'de' => 'Gruss'],
            ],
            'tagsEnabled' => null,
            'tags' => [],
        ]);

        // Assert
        $taxonomy = $form->getData();
        static::assertInstanceOf(Config::class, $taxonomy);
        static::assertTrue($taxonomy->isCategoriesEnabled());
        static::assertSame(
            [['id' => '0', 'labels' => ['en' => 'Greeting', 'de' => 'Gruss']]],
            $taxonomy->getCategories(),
        );
    }

    /** @param list<string> $codes */
    private function factory(array $codes): FormFactoryInterface
    {
        $languageService = $this->createStub(LanguageService::class);
        $languageService->method('getAdminFilteredEnabledCodes')->willReturn($codes);

        return Forms::createFormFactoryBuilder()
            ->addExtension(new PreloadedExtension([
                new TaxonomyConfigType(),
                new TaxonomyDefinitionType($languageService),
            ], []))
            ->getFormFactory();
    }
}
