<?php declare(strict_types=1);

namespace Plugin\Books\Form;

use Override;
use Plugin\Books\ValueObject\Config;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ConfigType extends AbstractType
{
    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('circulation', CheckboxType::class, [
                'label' => 'books_config.field_circulation',
                'help' => 'books_config.help_circulation',
                'required' => false,
            ])
            ->add('trustSystem', CheckboxType::class, [
                'label' => 'books_config.field_trust_system',
                'help' => 'books_config.help_trust_system',
                'required' => false,
                'row_attr' => ['class' => 'ml-5'],
            ]);
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Config::class,
        ]);
    }

    #[Override]
    public function getBlockPrefix(): string
    {
        return 'books_config';
    }
}
