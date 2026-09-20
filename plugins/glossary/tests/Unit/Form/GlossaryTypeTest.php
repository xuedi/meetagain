<?php declare(strict_types=1);

namespace Plugin\Glossary\Tests\Unit\Form;

use App\Item\Tag\AssignmentFormHelper;
use App\Item\Tag\TagService;
use App\Item\TranslationFormHelper;
use App\Service\Config\LanguageService;
use PHPUnit\Framework\TestCase;
use Plugin\Glossary\Entity\Glossary;
use Plugin\Glossary\Form\GlossaryType;
use Plugin\Glossary\Service\ConfigService;
use Plugin\Glossary\ValueObject\Config;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

class GlossaryTypeTest extends TestCase
{
    public function testNeutralConfigBuildsTheTermAndOneDefinitionPerLanguage(): void
    {
        // Arrange + Act
        $form = $this->formFor(new Config(), []);

        // Assert
        static::assertTrue($form->has('phrase'));
        static::assertTrue($form->has('definition-en'));
        static::assertTrue($form->has('definition-de'));
        static::assertFalse($form->has('secondary'));
        static::assertFalse($form->has(AssignmentFormHelper::TAGS_FIELD));
    }

    public function testSecondaryEnabledAddsTheSecondaryField(): void
    {
        // Arrange
        $config = new Config()
            ->setSecondaryEnabled(true)
            ->setSecondaryLabel('Romaji');

        // Act
        $form = $this->formFor($config, []);

        // Assert
        static::assertTrue($form->has('secondary'));
    }

    public function testAVocabularyAddsTheSharedTagAssignmentField(): void
    {
        // Arrange + Act
        $form = $this->formFor(new Config(), [3 => 'Greeting', 4 => 'Greeting / Formal']);

        // Assert
        $tags = $form->get(AssignmentFormHelper::TAGS_FIELD);
        static::assertTrue($tags->getConfig()->getOption('multiple'));
        static::assertTrue($tags->getConfig()->getOption('expanded'));
    }

    public function testTheDefinitionFieldsStartFromTheDraft(): void
    {
        // Arrange
        $draft = new Glossary()->submitDefinitions(['en' => 'Hello']);

        // Act
        $form = $this->formFor(new Config(), [], $draft);

        // Assert
        static::assertSame('Hello', $form->get('definition-en')->getData());
        static::assertSame('', $form->get('definition-de')->getData());
    }

    public function testSubmittingCollectsTheDefinitionsOntoTheDraft(): void
    {
        // Arrange
        $draft = new Glossary();
        $form = $this->formFor(new Config(), [], $draft);

        // Act
        $form->submit(['phrase' => '你好', 'definition-en' => ' Hello ', 'definition-de' => '']);

        // Assert
        static::assertTrue($form->isValid());
        static::assertSame(['en' => 'Hello', 'de' => ''], $draft->getSubmittedDefinitions());
    }

    public function testSubmittingWithoutAnyDefinitionIsRefused(): void
    {
        // Arrange
        $form = $this->formFor(new Config(), [], new Glossary());

        // Act
        $form->submit(['phrase' => '你好', 'definition-en' => '', 'definition-de' => ' ']);

        // Assert
        static::assertFalse($form->isValid());
        static::assertCount(1, $form->getErrors());
    }

    /** @param array<int, string> $choices */
    private function formFor(Config $config, array $choices, ?Glossary $data = null): FormInterface
    {
        $configService = $this->createStub(ConfigService::class);
        $configService->method('getConfig')->willReturn($config);

        $tagService = $this->createStub(TagService::class);
        $tagService->method('getAssignableChoices')->willReturn($choices);
        $tagService->method('getDepths')->willReturn([]);
        $tagService->method('getParents')->willReturn([]);

        $languageService = $this->createStub(LanguageService::class);
        $languageService->method('getAdminFilteredEnabledCodes')->willReturn(['en', 'de']);

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        $type = new GlossaryType(
            $configService,
            new AssignmentFormHelper($tagService, new RequestStack()),
            new TranslationFormHelper($languageService),
            $translator,
        );
        $factory = Forms::createFormFactoryBuilder()
            ->addExtension(new PreloadedExtension([$type], []))
            ->getFormFactory();

        return $factory->create(GlossaryType::class, $data);
    }
}
