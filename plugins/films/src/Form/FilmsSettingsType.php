<?php declare(strict_types=1);

namespace Plugin\Films\Form;

use Plugin\Films\Entity\ExternalSource;
use Plugin\Films\Entity\FilmsSettings;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

class FilmsSettingsType extends AbstractType
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {}

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('adapter', EnumType::class, [
            'class' => ExternalSource::class,
            'label' => 'films_settings.adapter_label',
            'placeholder' => $this->translator->trans('films_settings.adapter_none'),
            'required' => false,
            'expanded' => true,
            'choice_label' => fn(ExternalSource $e) => $this->translator->trans('films_settings.adapter_' . $e->value),
        ])->add('tmdbKey', PasswordType::class, [
            'label' => 'films_settings.tmdb_key_label',
            'mapped' => false,
            'required' => false,
            'always_empty' => true,
            'attr' => [
                'placeholder' => $options['tmdb_key_set'] ? $this->translator->trans('films_settings.key_already_set') : '',
                'autocomplete' => 'off',
            ],
        ])->add('clearTmdbKey', CheckboxType::class, [
            'label' => 'films_settings.clear_key_label',
            'mapped' => false,
            'required' => false,
        ])->add('omdbKey', PasswordType::class, [
            'label' => 'films_settings.omdb_key_label',
            'mapped' => false,
            'required' => false,
            'always_empty' => true,
            'attr' => [
                'placeholder' => $options['omdb_key_set'] ? $this->translator->trans('films_settings.key_already_set') : '',
                'autocomplete' => 'off',
            ],
        ])->add('clearOmdbKey', CheckboxType::class, [
            'label' => 'films_settings.clear_key_label',
            'mapped' => false,
            'required' => false,
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => FilmsSettings::class,
            'tmdb_key_set' => false,
            'omdb_key_set' => false,
        ]);
        $resolver->setAllowedTypes('tmdb_key_set', 'bool');
        $resolver->setAllowedTypes('omdb_key_set', 'bool');
    }
}
