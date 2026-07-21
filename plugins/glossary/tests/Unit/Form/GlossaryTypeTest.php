<?php declare(strict_types=1);

namespace Plugin\Glossary\Tests\Unit\Form;

use App\Item\Taxonomy\TaxonomyConfig;
use App\Service\Config\LanguageService;
use PHPUnit\Framework\TestCase;
use Plugin\Glossary\Form\GlossaryType;
use Plugin\Glossary\Service\ConfigService;
use Plugin\Glossary\ValueObject\Config;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\HttpFoundation\RequestStack;

class GlossaryTypeTest extends TestCase
{
    public function testNeutralConfigBuildsTermAndDefinitionOnly(): void
    {
        // Arrange
        $form = $this->formFor(new Config());

        // Assert
        static::assertTrue($form->has('phrase'));
        static::assertTrue($form->has('explanation'));
        static::assertFalse($form->has('pinyin'));
        static::assertFalse($form->has('category'));
    }

    public function testSecondaryEnabledAddsPinyinField(): void
    {
        // Arrange
        $config = (new Config())->setSecondaryEnabled(true)->setSecondaryLabel('Romaji');

        // Act
        $form = $this->formFor($config);

        // Assert
        static::assertTrue($form->has('pinyin'));
        static::assertFalse($form->has('category'));
    }

    public function testCategoriesAddCategoryChoiceField(): void
    {
        // Arrange
        $taxonomy = (new TaxonomyConfig())
            ->setCategoriesEnabled(true)
            ->setCategories([['id' => 0, 'labels' => ['en' => 'Greeting']]]);
        $config = (new Config())->setTaxonomy($taxonomy);

        // Act
        $form = $this->formFor($config);

        // Assert
        static::assertTrue($form->has('category'));
        static::assertFalse($form->has('pinyin'));
    }

    private function formFor(Config $config): \Symfony\Component\Form\FormInterface
    {
        $configService = $this->createStub(ConfigService::class);
        $configService->method('getConfig')->willReturn($config);

        return $this->factory($configService)->create(GlossaryType::class);
    }

    private function factory(ConfigService $configService): FormFactoryInterface
    {
        $languageService = $this->createStub(LanguageService::class);
        $languageService->method('getFilteredDefaultLocale')->willReturn('en');

        $type = new GlossaryType($configService, $languageService, new RequestStack());

        return Forms::createFormFactoryBuilder()
            ->addExtension(new PreloadedExtension([$type], []))
            ->getFormFactory();
    }
}
