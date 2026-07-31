<?php declare(strict_types=1);

namespace Tests\Functional\Item\Taxonomy;

use App\Item\Taxonomy\AssignmentFormHelper;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormView;
use Twig\Environment;

class AssignmentFormThemeTest extends KernelTestCase
{
    private const array GROUPED_CHOICES = ['Soup' => 6, 'Meat' => ['Chicken' => 5, 'Beef' => 7]];

    public function testTheCategorySelectRendersGroupedEntriesAsAnOptgroup(): void
    {
        // Arrange
        $form = $this->factory()->createBuilder(options: ['csrf_protection' => false])
            ->add(AssignmentFormHelper::CATEGORY_FIELD, ChoiceType::class, [
                'choices' => self::GROUPED_CHOICES,
                'placeholder' => 'item.taxonomy_category_none',
                'required' => false,
            ])
            ->getForm()
            ->createView();

        // Act
        $html = $this->twig()->createTemplate('{{ form_widget(form.' . AssignmentFormHelper::CATEGORY_FIELD . ') }}')->render(['form' => $form]);

        // Assert
        static::assertStringContainsString('<optgroup label="Meat">', $html);
        static::assertMatchesRegularExpression('/<option value="6"[^>]*>Soup<\/option>.*<optgroup/s', $html, 'Ungrouped entries come first');
        static::assertSame(1, substr_count($html, '<optgroup'), 'Grouping is one level deep');
    }

    public function testAFlatTagListKeepsEveryCheckboxUnwrapped(): void
    {
        // Arrange
        $form = $this->tagsForm(['Spicy' => 1, 'Sweet' => 2], tree: false);

        // Act
        $html = $this->render($form);

        // Assert
        static::assertStringNotContainsString('padding-left', $html);
    }

    public function testASubTagCheckboxIsIndentedAndNamesItsParent(): void
    {
        // Arrange
        $form = $this->tagsForm(['Meat' => 1, 'Chicken' => 4], tree: true, parents: [1 => null, 4 => 1], depths: [1 => 1, 4 => 2]);

        // Act
        $html = $this->render($form);

        // Assert
        static::assertStringContainsString('padding-left: 0rem', $html);
        static::assertStringContainsString('padding-left: 1.5rem', $html);
        static::assertMatchesRegularExpression('/data-taxonomy-parent="1"[^>]*value="4"/', $html);
    }

    /**
     * @param array<string, int> $choices
     * @param array<int, ?int>   $parents
     * @param array<int, int>    $depths
     */
    private function tagsForm(array $choices, bool $tree, array $parents = [], array $depths = []): FormView
    {
        return $this->factory()->createBuilder(options: ['csrf_protection' => false])
            ->add(AssignmentFormHelper::TAGS_FIELD, ChoiceType::class, [
                'choices' => $choices,
                'choice_attr' => static fn(int $id): array => [
                    'data-taxonomy-depth' => $depths[$id] ?? 1,
                    'data-taxonomy-parent' => (string) ($parents[$id] ?? null),
                ],
                'attr' => $tree ? ['data-taxonomy-tree' => ''] : [],
                'block_prefix' => 'item_taxonomy_tags',
                'multiple' => true,
                'expanded' => true,
                'required' => false,
            ])
            ->getForm()
            ->createView();
    }

    private function render(FormView $form): string
    {
        return $this->twig()
            ->createTemplate('{{ form_widget(form.' . AssignmentFormHelper::TAGS_FIELD . ') }}')
            ->render(['form' => $form]);
    }

    private function factory(): FormFactoryInterface
    {
        return static::getContainer()->get(FormFactoryInterface::class);
    }

    private function twig(): Environment
    {
        return static::getContainer()->get(Environment::class);
    }
}
