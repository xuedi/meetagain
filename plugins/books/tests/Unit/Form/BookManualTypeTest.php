<?php declare(strict_types=1);

namespace Plugin\Books\Tests\Unit\Form;

use App\Item\Tag\AssignmentFormHelper;
use App\Item\Tag\TagService;
use PHPUnit\Framework\TestCase;
use Plugin\Books\Form\BookManualType;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Validator\Validation;
use Symfony\Contracts\Translation\TranslatorInterface;

class BookManualTypeTest extends TestCase
{
    public function testAVocabularyAddsTheTagAssignmentField(): void
    {
        // Act
        $form = $this->formFor([20 => 'Fiction', 21 => 'Fiction / Science fiction']);

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
        $tagService->method('getChoices')->willReturn($choices);
        $tagService->method('getDepths')->willReturn([]);
        $tagService->method('getParents')->willReturn([]);

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        $type = new BookManualType($translator, new AssignmentFormHelper($tagService, new RequestStack()));

        return Forms::createFormFactoryBuilder()
            ->addExtension(new ValidatorExtension(Validation::createValidator()))
            ->addExtension(new PreloadedExtension([$type], []))
            ->getFormFactory()
            ->create(BookManualType::class);
    }
}
