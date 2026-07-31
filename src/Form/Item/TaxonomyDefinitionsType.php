<?php declare(strict_types=1);

namespace App\Form\Item;

use App\Item\Taxonomy\Axis;
use App\Item\Taxonomy\Config;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TaxonomyDefinitionsType extends AbstractType
{
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        if ($options['with_categories'] === true) {
            $builder->add('categories', CollectionType::class, $this->collection($options, Axis::Category));
        }

        if ($options['with_tags'] === true) {
            $builder->add('tags', CollectionType::class, $this->collection($options, Axis::Tag));
        }
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
     * @return array<string, mixed>
     */
    private function collection(array $options, Axis $axis): array
    {
        return [
            'label' => false,
            'entry_type' => TaxonomyDefinitionType::class,
            'entry_options' => ['usage' => (array) ($options['usage'][$axis->value] ?? [])],
            'allow_add' => true,
            'allow_delete' => true,
            'by_reference' => false,
            'required' => false,
            'prototype' => true,
        ];
    }
}
