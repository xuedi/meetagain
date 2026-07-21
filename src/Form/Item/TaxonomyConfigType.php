<?php declare(strict_types=1);

namespace App\Form\Item;

use App\Item\Taxonomy\TaxonomyConfig;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * The embeddable taxonomy fragment a plugin Config form adds with one
 * ->add('taxonomy', TaxonomyConfigType::class). Two independent enable toggles each gate a
 * per-language definition collection. Rendered by the _form/taxonomy.html.twig form theme
 * (language toggle + enable gating).
 */
class TaxonomyConfigType extends AbstractType
{
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('categoriesEnabled', CheckboxType::class, [
            'label' => 'item.taxonomy_categories_enable',
            'required' => false,
        ])->add('categories', CollectionType::class, [
            'label' => false,
            'entry_type' => TaxonomyDefinitionType::class,
            'allow_add' => true,
            'allow_delete' => true,
            'by_reference' => false,
            'required' => false,
            'prototype' => true,
        ])->add('tagsEnabled', CheckboxType::class, [
            'label' => 'item.taxonomy_tags_enable',
            'required' => false,
        ])->add('tags', CollectionType::class, [
            'label' => false,
            'entry_type' => TaxonomyDefinitionType::class,
            'allow_add' => true,
            'allow_delete' => true,
            'by_reference' => false,
            'required' => false,
            'prototype' => true,
        ]);
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => TaxonomyConfig::class,
        ]);
    }

    #[\Override]
    public function getBlockPrefix(): string
    {
        return 'item_taxonomy_config';
    }
}
