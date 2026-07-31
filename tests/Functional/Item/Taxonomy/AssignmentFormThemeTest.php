<?php declare(strict_types=1);

namespace Tests\Functional\Item\Taxonomy;

use App\Item\Taxonomy\AssignmentFormHelper;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormFactoryInterface;
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

    public function testTheTagCheckboxesRenderUnderABoldIndentedGroupHeading(): void
    {
        // Arrange
        $form = $this->factory()->createBuilder(options: ['csrf_protection' => false])
            ->add(AssignmentFormHelper::TAGS_FIELD, ChoiceType::class, [
                'choices' => self::GROUPED_CHOICES,
                'block_prefix' => 'item_taxonomy_tags',
                'multiple' => true,
                'expanded' => true,
                'required' => false,
            ])
            ->getForm()
            ->createView();

        // Act
        $html = $this->twig()->createTemplate('{{ form_widget(form.' . AssignmentFormHelper::TAGS_FIELD . ') }}')->render(['form' => $form]);

        // Assert
        static::assertSame(3, substr_count($html, 'type="checkbox"'), 'Every definition keeps its own checkbox');
        static::assertStringContainsString('<p class="has-text-weight-semibold mt-2">Meat</p>', $html);
        static::assertMatchesRegularExpression('/Soup.*Meat.*<div class="ml-4">.*Chicken.*Beef/s', $html);
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
