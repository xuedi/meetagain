<?php declare(strict_types=1);

namespace Plugin\Glossary\Form;

use App\Form\Item\TaxonomyConfigType;
use Plugin\Glossary\ValueObject\Config;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ConfigType extends AbstractType
{
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('primaryLabel', TextType::class, [
            'label' => 'glossary_config.primary_label',
            'required' => false,
            'help' => 'glossary_config.primary_label_help',
        ])->add('definitionLabel', TextType::class, [
            'label' => 'glossary_config.definition_label',
            'required' => false,
            'help' => 'glossary_config.definition_label_help',
        ])->add('secondaryEnabled', CheckboxType::class, [
            'label' => 'glossary_config.secondary_enabled',
            'required' => false,
        ])->add('secondaryLabel', TextType::class, [
            'label' => 'glossary_config.secondary_label',
            'required' => false,
            'help' => 'glossary_config.secondary_label_help',
        ])->add('taxonomy', TaxonomyConfigType::class);
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Config::class,
        ]);
    }

    #[\Override]
    public function getBlockPrefix(): string
    {
        return 'glossary_config';
    }
}
