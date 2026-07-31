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
    private const string GROUP_FIELD = 'categoryGroups';

    public function __construct(
        private readonly LanguageService $languageService,
    ) {}

    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $config = $builder->getData();
        $taxonomy = $config instanceof Config ? $config : null;

        foreach ($this->axes($options) as $axis) {
            if ($axis === Axis::Category) {
                $builder->add(self::GROUP_FIELD, CollectionType::class, $this->collection($options, $axis, null));
            }

            $builder->add($this->definitionField($axis), CollectionType::class, $this->collection($options, $axis, [
                'group_choices' => $axis === Axis::Category ? $this->groupChoices($taxonomy) : null,
                'parent_choices' => $this->parentChoices($taxonomy, $axis),
                'depths' => $this->depths($taxonomy, $axis),
            ]));
        }

        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (PreSubmitEvent $event) use ($options, $taxonomy): void {
            $submitted = (array) $event->getData();
            foreach ($this->axes($options) as $axis) {
                $rows = (array) ($submitted[$this->definitionField($axis)] ?? []);
                $event->getForm()->add($this->definitionField($axis), CollectionType::class, $this->collection($options, $axis, [
                    'group_choices' => $axis === Axis::Category
                        ? $this->submittedRowChoices((array) ($submitted[self::GROUP_FIELD] ?? []))
                        : null,
                    'parent_choices' => $this->parentChoices($taxonomy, $axis) === null ? null : $this->submittedRowChoices($rows),
                    'depths' => $this->depths($taxonomy, $axis),
                ]));
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
     * @param array{group_choices: ?array<string, int|string>, parent_choices: ?array<string, int|string>, depths: array<int, int>}|null $entry
     * @return array<string, mixed>
     */
    private function collection(array $options, Axis $axis, ?array $entry): array
    {
        return [
            'label' => false,
            'entry_type' => TaxonomyDefinitionType::class,
            'entry_options' => [
                'usage' => $entry === null ? [] : (array) ($options['usage'][$axis->value] ?? []),
                'group_choices' => $entry['group_choices'] ?? null,
                'parent_choices' => $entry['parent_choices'] ?? null,
                'depths' => $entry['depths'] ?? [],
            ],
            'allow_add' => true,
            'allow_delete' => true,
            'by_reference' => false,
            'required' => false,
            'prototype' => true,
        ];
    }

    /** @return array<string, int|string> */
    private function groupChoices(?Config $taxonomy): array
    {
        if ($taxonomy === null) {
            return [];
        }

        $sourceLocale = $this->languageService->getFilteredDefaultLocale();

        $choices = [];
        foreach ($taxonomy->categoryGroupDefinitions() as $group) {
            $choices[$group->labelFor(null, $sourceLocale)] = $group->id;
        }

        return $choices;
    }

    /** @return array<string, int|string>|null */
    private function parentChoices(?Config $taxonomy, Axis $axis): ?array
    {
        if ($axis !== Axis::Tag || $taxonomy === null || $taxonomy->getTagDepth() < 2) {
            return null;
        }

        $sourceLocale = $this->languageService->getFilteredDefaultLocale();
        $tree = $taxonomy->tagTree();

        $choices = [];
        foreach ($tree->ordered() as $tag) {
            $depth = $tree->depthOf($tag->id);
            if ($depth >= $taxonomy->getTagDepth()) {
                continue;
            }

            $label = str_repeat('- ', $depth - 1) . $tag->labelFor(null, $sourceLocale);
            $choices[isset($choices[$label]) ? $label . ' #' . $tag->id : $label] = $tag->id;
        }

        return $choices;
    }

    /** @return array<int, int> tag id => how many levels deep it sits */
    private function depths(?Config $taxonomy, Axis $axis): array
    {
        if ($axis !== Axis::Tag || $taxonomy === null) {
            return [];
        }

        $tree = $taxonomy->tagTree();

        $depths = [];
        foreach ($taxonomy->tagDefinitions() as $tag) {
            $depths[$tag->id] = $tree->depthOf($tag->id);
        }

        return $depths;
    }

    /**
     * @param array<array-key, mixed> $rows submitted rows of one axis, whose ids may be client tokens
     * @return array<string, int|string>
     */
    private function submittedRowChoices(array $rows): array
    {
        $choices = [];
        foreach ($rows as $row) {
            $id = is_array($row) ? (string) ($row['id'] ?? '') : '';
            if ($id === '') {
                continue;
            }

            $labels = array_filter(array_map(strval(...), array_diff_key((array) $row, ['id' => null, 'group' => null, 'parentTag' => null])));
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

    private function definitionField(Axis $axis): string
    {
        return $axis === Axis::Category ? 'categories' : 'tags';
    }
}
