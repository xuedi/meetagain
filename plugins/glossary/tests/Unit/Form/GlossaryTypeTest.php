<?php declare(strict_types=1);

namespace Plugin\Glossary\Tests\Unit\Form;

use PHPUnit\Framework\TestCase;
use Plugin\Glossary\Form\GlossaryType;
use Plugin\Glossary\Service\ConfigService;
use Plugin\Glossary\ValueObject\Config;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\PreloadedExtension;

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
        $config = (new Config())->setCategories([['id' => 0, 'label' => 'Greeting']]);

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
        return Forms::createFormFactoryBuilder()
            ->addExtension(new PreloadedExtension([new GlossaryType($configService)], []))
            ->getFormFactory();
    }
}
