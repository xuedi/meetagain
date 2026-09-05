<?php declare(strict_types=1);

namespace Plugin\Films\Tests\Unit\Form;

use App\Item\Tag\AssignmentFormHelper;
use App\Item\Tag\TagService;
use PHPUnit\Framework\TestCase;
use Plugin\Films\Form\FilmManualType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

class FilmManualTypeTest extends TestCase
{
    public function testAVocabularyAddsTheTagAssignmentField(): void
    {
        // Act
        $form = $this->formFor([1 => 'Drama', 2 => 'Drama / Family drama']);

        // Assert
        $tags = $form->get(AssignmentFormHelper::TAGS_FIELD);
        static::assertTrue($tags->getConfig()->getOption('multiple'));
        static::assertTrue($tags->getConfig()->getOption('expanded'));
    }

    public function testWithoutAVocabularyThereIsNoTagField(): void
    {
        // Act
        $form = $this->formFor([]);

        // Assert
        static::assertTrue($form->has('title'));
        static::assertFalse($form->has(AssignmentFormHelper::TAGS_FIELD));
    }

    /** @param array<int, string> $choices */
    private function formFor(array $choices): FormInterface
    {
        $tagService = $this->createStub(TagService::class);
        $tagService->method('getAssignableChoices')->willReturn($choices);
        $tagService->method('getDepths')->willReturn([]);
        $tagService->method('getParents')->willReturn([]);

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        $type = new FilmManualType($translator, new AssignmentFormHelper($tagService, new RequestStack()));

        return Forms::createFormFactoryBuilder()
            ->addExtension(new PreloadedExtension([$type], []))
            ->getFormFactory()
            ->create(FilmManualType::class);
    }
}
