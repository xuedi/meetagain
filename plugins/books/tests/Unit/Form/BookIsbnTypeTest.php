<?php declare(strict_types=1);

namespace Plugin\Books\Tests\Unit\Form;

use App\Item\Tag\AssignmentFormHelper;
use App\Item\Tag\TagService;
use PHPUnit\Framework\TestCase;
use Plugin\Books\Form\BookIsbnType;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Validator\Validation;
use Symfony\Contracts\Translation\TranslatorInterface;

class BookIsbnTypeTest extends TestCase
{
    public function testAVocabularyAddsTheTagAssignmentField(): void
    {
        // Act
        $form = $this->formFor([20 => 'Fiction']);

        // Assert
        static::assertTrue($form->has('isbn'));
        static::assertTrue($form->get(AssignmentFormHelper::TAGS_FIELD)->getConfig()->getOption('multiple'));
    }

    public function testWithoutAVocabularyTheLookupStaysASingleField(): void
    {
        // Act
        $form = $this->formFor([]);

        // Assert
        static::assertTrue($form->has('isbn'));
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

        $type = new BookIsbnType($translator, new AssignmentFormHelper($tagService, new RequestStack()));

        return Forms::createFormFactoryBuilder()
            ->addExtension(new ValidatorExtension(Validation::createValidator()))
            ->addExtension(new PreloadedExtension([$type], []))
            ->getFormFactory()
            ->create(BookIsbnType::class);
    }
}
