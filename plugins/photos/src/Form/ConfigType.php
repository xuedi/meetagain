<?php declare(strict_types=1);

namespace Plugin\Photos\Form;

use Override;
use Plugin\Photos\ValueObject\Config;
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
            ->add('memberUploads', CheckboxType::class, [
                'label' => 'photos_config.field_member_uploads',
                'help' => 'photos_config.help_member_uploads',
                'required' => false,
            ])
            ->add('showCameraMeta', CheckboxType::class, [
                'label' => 'photos_config.field_show_camera_meta',
                'help' => 'photos_config.help_show_camera_meta',
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
        return 'photos_config';
    }
}
