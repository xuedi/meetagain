<?php declare(strict_types=1);

namespace Plugin\Boardgames\Form;

use Override;
use Plugin\Boardgames\Enum\ExternalSource;
use Plugin\Boardgames\ValueObject\Config;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
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
        $builder
            ->add('adapter', EnumType::class, [
                'class' => ExternalSource::class,
                'label' => 'boardgames_config.adapter_label',
                'placeholder' => $this->translator->trans('boardgames_config.adapter_none'),
                'required' => false,
                'expanded' => true,
                'choice_label' => fn(ExternalSource $source) => $this->translator->trans('boardgames_config.adapter_' . $source->value),
            ])
            ->add('bggToken', PasswordType::class, [
                'label' => 'boardgames_config.bgg_token_label',
                'help' => 'boardgames_config.bgg_token_help',
                'mapped' => false,
                'required' => false,
                'always_empty' => true,
                'attr' => [
                    'placeholder' => $options['bgg_token_set'] ? $this->translator->trans('boardgames_config.token_already_set') : '',
                    'autocomplete' => 'off',
                ],
            ])
            ->add('clearBggToken', CheckboxType::class, [
                'label' => 'boardgames_config.clear_token_label',
                'mapped' => false,
                'required' => false,
            ])
            ->add('circulation', CheckboxType::class, [
                'label' => 'boardgames_config.field_circulation',
                'help' => 'boardgames_config.help_circulation',
                'required' => false,
            ])
            ->add('trustSystem', CheckboxType::class, [
                'label' => 'boardgames_config.field_trust_system',
                'help' => 'boardgames_config.help_trust_system',
                'required' => false,
                'row_attr' => ['class' => 'ml-5'],
            ]);
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Config::class,
            'bgg_token_set' => false,
        ]);
        $resolver->setAllowedTypes('bgg_token_set', 'bool');
    }

    #[Override]
    public function getBlockPrefix(): string
    {
        return 'boardgames_config';
    }
}
