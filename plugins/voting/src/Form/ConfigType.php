<?php declare(strict_types=1);

namespace Plugin\Voting\Form;

use Plugin\Voting\Enum\ChoiceMode;
use Plugin\Voting\ValueObject\Config;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ConfigType extends AbstractType
{
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('defaultDurationDays', IntegerType::class, [
            'label' => 'voting_config.default_duration_days',
            'help' => 'voting_config.default_duration_days_help',
            'attr' => ['min' => 1, 'max' => 365],
            'required' => true,
        ])->add('choiceMode', EnumType::class, [
            'label' => 'voting_config.choice_mode',
            'help' => 'voting_config.choice_mode_help',
            'class' => ChoiceMode::class,
            'choice_label' => static fn(ChoiceMode $mode): string => 'voting_config.choice_mode_' . $mode->value,
            'expanded' => true,
            'required' => true,
        ]);
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
        return 'voting_config';
    }
}
