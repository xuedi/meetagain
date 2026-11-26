<?php declare(strict_types=1);

namespace Plugin\Wishlist\Form;

use Override;
use Plugin\Wishlist\ValueObject\Config;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ConfigType extends AbstractType
{
    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('navLink', CheckboxType::class, [
            'label' => 'wishlist_config.field_nav_link',
            'help' => 'wishlist_config.help_nav_link',
            'required' => false,
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
        return 'wishlist_config';
    }
}
