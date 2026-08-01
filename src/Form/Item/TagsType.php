<?php declare(strict_types=1);

namespace App\Form\Item;

use Override;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Event\PreSubmitEvent;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TagsType extends AbstractType
{
    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('tags', CollectionType::class, $this->collection($options, $options['parent_choices']));

        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (PreSubmitEvent $event) use ($options): void {
            $data = (array) $event->getData();
            $rows = (array) ($data['tags'] ?? []);
            $event->getForm()->add('tags', CollectionType::class, $this->collection($options, $this->submittedRowChoices($rows)));
        });
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'usage' => [],
            'depths' => [],
            'parent_choices' => [],
        ]);
        $resolver->setAllowedTypes('usage', 'array');
        $resolver->setAllowedTypes('depths', 'array');
        $resolver->setAllowedTypes('parent_choices', 'array');
    }

    #[Override]
    public function getBlockPrefix(): string
    {
        return 'item_tags_editor';
    }

    /**
     * @param  array<string, mixed>       $options
     * @param  array<string, int|string>  $parentChoices
     * @return array<string, mixed>
     */
    private function collection(array $options, array $parentChoices): array
    {
        return [
            'label' => false,
            'entry_type' => TagRowType::class,
            'entry_options' => [
                'usage' => $options['usage'],
                'depths' => $options['depths'],
                'parent_choices' => $parentChoices,
            ],
            'allow_add' => true,
            'allow_delete' => true,
            'by_reference' => false,
            'required' => false,
            'prototype' => true,
        ];
    }

    /**
     * @param  array<array-key, mixed>  $rows submitted rows, whose ids may be client tokens
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

            $labels = array_filter(array_map(strval(...), array_diff_key((array) $row, ['id' => null, 'parent' => null])));
            $label = $labels === [] ? $id : reset($labels);
            $choices[isset($choices[$label]) ? $id : $label] = $id;
        }

        return $choices;
    }
}
