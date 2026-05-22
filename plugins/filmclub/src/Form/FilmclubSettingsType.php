<?php declare(strict_types=1);

namespace Plugin\Filmclub\Form;

use Plugin\Filmclub\Entity\ExternalSource;
use Plugin\Filmclub\Entity\FilmclubSettings;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

class FilmclubSettingsType extends AbstractType
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {}

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('adapter', EnumType::class, [
            'class' => ExternalSource::class,
            'label' => 'filmclub_settings.adapter_label',
            'placeholder' => $this->translator->trans('filmclub_settings.adapter_none'),
            'required' => false,
            'expanded' => true,
            'choice_label' => fn(ExternalSource $e) => $this->translator->trans('filmclub_settings.adapter_'
            . $e->value),
        ])->add('tmdbKey', PasswordType::class, [
            'label' => 'filmclub_settings.tmdb_key_label',
            'mapped' => false,
            'required' => false,
            'always_empty' => true,
            'attr' => [
                'placeholder' => $options['tmdb_key_set']
                    ? $this->translator->trans('filmclub_settings.key_already_set')
                    : '',
                'autocomplete' => 'off',
            ],
        ])->add('clearTmdbKey', CheckboxType::class, [
            'label' => 'filmclub_settings.clear_key_label',
            'mapped' => false,
            'required' => false,
        ])->add('omdbKey', PasswordType::class, [
            'label' => 'filmclub_settings.omdb_key_label',
            'mapped' => false,
            'required' => false,
            'always_empty' => true,
            'attr' => [
                'placeholder' => $options['omdb_key_set']
                    ? $this->translator->trans('filmclub_settings.key_already_set')
                    : '',
                'autocomplete' => 'off',
            ],
        ])->add('clearOmdbKey', CheckboxType::class, [
            'label' => 'filmclub_settings.clear_key_label',
            'mapped' => false,
            'required' => false,
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => FilmclubSettings::class,
            'tmdb_key_set' => false,
            'omdb_key_set' => false,
        ]);
        $resolver->setAllowedTypes('tmdb_key_set', 'bool');
        $resolver->setAllowedTypes('omdb_key_set', 'bool');
    }
}
