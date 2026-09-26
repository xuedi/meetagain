<?php declare(strict_types=1);

namespace Plugin\Karaoke\Form;

use Override;
use Plugin\Karaoke\ValueObject\Config;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

class ConfigType extends AbstractType
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {}

    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('lookupEnabled', CheckboxType::class, [
            'label' => 'karaoke_config.field_lookup_enabled',
            'help' => 'karaoke_config.help_lookup_enabled',
            'required' => false,
        ])->add('lrclibEnabled', CheckboxType::class, [
            'label' => 'karaoke_config.field_lrclib_enabled',
            'help' => 'karaoke_config.help_lrclib_enabled',
            'required' => false,
            'row_attr' => ['class' => 'ml-5'],
        ])->add('youtubeApiKey', PasswordType::class, [
            'label' => 'karaoke_config.youtube_api_key_label',
            'help' => 'karaoke_config.youtube_api_key_help',
            'mapped' => false,
            'required' => false,
            'always_empty' => true,
            'attr' => [
                'placeholder' => $options['youtube_api_key_set'] ? $this->translator->trans('karaoke_config.key_already_set') : '',
                'autocomplete' => 'off',
            ],
        ])->add('clearYoutubeApiKey', CheckboxType::class, [
            'label' => 'karaoke_config.clear_key_label',
            'mapped' => false,
            'required' => false,
        ]);
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Config::class,
            'youtube_api_key_set' => false,
        ]);
        $resolver->setAllowedTypes('youtube_api_key_set', 'bool');
    }

    #[Override]
    public function getBlockPrefix(): string
    {
        return 'karaoke_config';
    }
}
