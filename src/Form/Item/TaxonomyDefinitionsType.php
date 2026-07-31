<?php declare(strict_types=1);

namespace App\Form\Item;

use App\Item\Taxonomy\Axis;
use App\Item\Taxonomy\Config;
use App\Service\Config\LanguageService;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Event\PreSubmitEvent;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TaxonomyDefinitionsType extends AbstractType
{
    public function __construct(
        private readonly LanguageService $languageService,
    ) {}

    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $config = $builder->getData();
        $taxonomy = $config instanceof Config ? $config : null;

        foreach ($this->axes($options) as $axis) {
            $builder->add($this->groupField($axis), CollectionType::class, $this->collection($options, $axis, null));
            $builder->add($this->definitionField($axis), CollectionType::class, $this->collection($options, $axis, $this->groupChoices($taxonomy, $axis)));
        }

        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (PreSubmitEvent $event) use ($options): void {
            $submitted = (array) $event->getData();
            foreach ($this->axes($options) as $axis) {
                $event->getForm()->add(
                    $this->definitionField($axis),
                    CollectionType::class,
                    $this->collection($options, $axis, $this->submittedGroupChoices((array) ($submitted[$this->groupField($axis)] ?? []))),
                );
            }
        });
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Config::class,
            'with_categories' => true,
            'with_tags' => true,
            'usage' => [],
        ]);
        $resolver->setAllowedTypes('with_categories', 'bool');
        $resolver->setAllowedTypes('with_tags', 'bool');
        $resolver->setAllowedTypes('usage', 'array');
    }

    #[\Override]
    public function getBlockPrefix(): string
    {
        return 'item_taxonomy_definitions';
    }

    /**
     * @param array<string, mixed> $options
     * @param array<string, int|string>|null $groupChoices
     * @return array<string, mixed>
     */
    private function collection(array $options, Axis $axis, ?array $groupChoices): array
    {
        return [
            'label' => false,
            'entry_type' => TaxonomyDefinitionType::class,
            'entry_options' => [
                'usage' => $groupChoices === null ? [] : (array) ($options['usage'][$axis->value] ?? []),
                'group_choices' => $groupChoices,
            ],
            'allow_add' => true,
            'allow_delete' => true,
            'by_reference' => false,
            'required' => false,
            'prototype' => true,
        ];
    }

    /** @return array<string, int|string> */
    private function groupChoices(?Config $taxonomy, Axis $axis): array
    {
        if ($taxonomy === null) {
            return [];
        }

        $sourceLocale = $this->languageService->getFilteredDefaultLocale();

        $choices = [];
        foreach ($taxonomy->groupDefinitions($axis) as $group) {
            $choices[$group->labelFor(null, $sourceLocale)] = $group->id;
        }

        return $choices;
    }

    /**
     * @param array<array-key, mixed> $rows the axis' submitted group rows, whose ids may be client tokens
     * @return array<string, int|string>
     */
    private function submittedGroupChoices(array $rows): array
    {
        $choices = [];
        foreach ($rows as $row) {
            $id = is_array($row) ? (string) ($row['id'] ?? '') : '';
            if ($id === '') {
                continue;
            }

            $labels = array_filter(array_map(strval(...), array_diff_key((array) $row, ['id' => null, 'group' => null])));
            $label = $labels === [] ? $id : reset($labels);
            $choices[isset($choices[$label]) ? $id : $label] = $id;
        }

        return $choices;
    }

    /**
     * @param array<string, mixed> $options
     * @return list<Axis>
     */
    private function axes(array $options): array
    {
        $axes = [];
        if ($options['with_categories'] === true) {
            $axes[] = Axis::Category;
        }
        if ($options['with_tags'] === true) {
            $axes[] = Axis::Tag;
        }

        return $axes;
    }

    private function groupField(Axis $axis): string
    {
        return $axis === Axis::Category ? 'categoryGroups' : 'tagGroups';
    }

    private function definitionField(Axis $axis): string
    {
        return $axis === Axis::Category ? 'categories' : 'tags';
    }
}
