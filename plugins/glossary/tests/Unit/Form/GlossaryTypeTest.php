<?php declare(strict_types=1);

namespace Plugin\Glossary\Tests\Unit\Form;

use App\Item\Tag\AssignmentFormHelper;
use App\Item\Tag\TagService;
use PHPUnit\Framework\TestCase;
use Plugin\Glossary\Form\GlossaryType;
use Plugin\Glossary\Service\ConfigService;
use Plugin\Glossary\ValueObject\Config;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\HttpFoundation\RequestStack;

class GlossaryTypeTest extends TestCase
{
    public function testNeutralConfigBuildsTermAndDefinitionOnly(): void
    {
        // Arrange
        $form = $this->formFor(new Config(), []);

        // Assert
        static::assertTrue($form->has('phrase'));
        static::assertTrue($form->has('explanation'));
        static::assertFalse($form->has('pinyin'));
        static::assertFalse($form->has(AssignmentFormHelper::TAGS_FIELD));
    }

    public function testSecondaryEnabledAddsPinyinField(): void
    {
        // Arrange
        $config = (new Config())->setSecondaryEnabled(true)->setSecondaryLabel('Romaji');

        // Act
        $form = $this->formFor($config, []);

        // Assert
        static::assertTrue($form->has('pinyin'));
        static::assertFalse($form->has(AssignmentFormHelper::TAGS_FIELD));
    }

    public function testAVocabularyAddsTheSharedTagAssignmentField(): void
    {
        // Arrange + Act
        $form = $this->formFor(new Config(), [3 => 'Greeting', 4 => 'Greeting / Formal']);

        // Assert
        $tags = $form->get(AssignmentFormHelper::TAGS_FIELD);
        static::assertTrue($tags->getConfig()->getOption('multiple'));
        static::assertTrue($tags->getConfig()->getOption('expanded'));
        static::assertFalse($form->has('pinyin'));
    }

    /** @param array<int, string> $choices */
    private function formFor(Config $config, array $choices): FormInterface
    {
        $configService = $this->createStub(ConfigService::class);
        $configService->method('getConfig')->willReturn($config);

        $tagService = $this->createStub(TagService::class);
        $tagService->method('getAssignableChoices')->willReturn($choices);
        $tagService->method('getDepths')->willReturn([]);
        $tagService->method('getParents')->willReturn([]);

        $type = new GlossaryType($configService, new AssignmentFormHelper($tagService, new RequestStack()));
        $factory = Forms::createFormFactoryBuilder()
            ->addExtension(new PreloadedExtension([$type], []))
            ->getFormFactory();

        return $factory->create(GlossaryType::class);
    }
}
