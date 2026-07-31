<?php declare(strict_types=1);

namespace App\Form\Item;

use App\Item\Taxonomy\Config;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TaxonomyConfigType extends AbstractType
{
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('categoriesEnabled', CheckboxType::class, [
            'label' => 'item.taxonomy_categories_enable',
            'required' => false,
        ])->add('tagsEnabled', CheckboxType::class, [
            'label' => 'item.taxonomy_tags_enable',
            'required' => false,
            'attr' => ['data-taxonomy-tags-toggle' => ''],
        ])->add('tagDepth', IntegerType::class, [
            'label' => 'item.taxonomy_tag_depth_label',
            'help' => 'item.taxonomy_tag_depth_help',
            'required' => false,
            'attr' => ['min' => 1, 'max' => Config::MAX_TAG_DEPTH],
        ]);
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Config::class,
            'label' => 'item.taxonomy_settings_heading',
            'help' => 'item.taxonomy_settings_help',
        ]);
    }

    #[\Override]
    public function getBlockPrefix(): string
    {
        return 'item_taxonomy_config';
    }
}
