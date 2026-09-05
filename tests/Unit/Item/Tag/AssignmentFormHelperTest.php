<?php declare(strict_types=1);

namespace App\Tests\Unit\Item\Tag;

use App\Item\Tag\AssignmentFormHelper;
use App\Item\Tag\TagService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\HttpFoundation\RequestStack;

class AssignmentFormHelperTest extends TestCase
{
    private const string TYPE = 'photo';

    public function testTheFieldOffersTheAssignableTagsOnly(): void
    {
        // Act
        $form = $this->formFor(null);

        // Assert
        static::assertSame(['Landscape' => 1, 'Portrait' => 3], $form->get(AssignmentFormHelper::TAGS_FIELD)->getConfig()->getOption('choices'));
    }

    public function testAManagedAssignmentIsNeitherPreselectedNorSubmittedBack(): void
    {
        // Arrange
        $form = $this->formFor(7);

        // Act
        $preselected = $form->get(AssignmentFormHelper::TAGS_FIELD)->getData();

        // Assert
        static::assertSame([1], $preselected);
    }

    public function testAVocabularyOfManagedRowsOnlyAddsNoFieldAtAll(): void
    {
        // Act
        $form = $this->formFor(null, choices: []);

        // Assert
        static::assertFalse($form->has(AssignmentFormHelper::TAGS_FIELD));
    }

    /** @param array<int, string> $choices */
    private function formFor(?int $itemId, array $choices = [1 => 'Landscape', 3 => 'Portrait']): FormInterface
    {
        $tagService = $this->createStub(TagService::class);
        $tagService->method('getAssignableChoices')->willReturn($choices);
        $tagService->method('getDepths')->willReturn([1 => 1, 2 => 1, 3 => 1]);
        $tagService->method('getParents')->willReturn([1 => null, 2 => null, 3 => null]);
        $tagService->method('getTagIds')->willReturn([1, 2]);

        $builder = Forms::createFormFactory()->createBuilder(FormType::class);
        new AssignmentFormHelper($tagService, new RequestStack())->addAssignmentFields($builder, self::TYPE, $itemId);

        return $builder->getForm();
    }
}
