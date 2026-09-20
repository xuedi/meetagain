<?php declare(strict_types=1);

namespace Plugin\Glossary\Form;

use Override;
use Plugin\Glossary\Enum\AnswerMode;
use Plugin\Glossary\Enum\Direction;
use Plugin\Glossary\ValueObject\Config;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ConfigType extends AbstractType
{
    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('primaryLabel', TextType::class, [
                'label' => 'glossary_config.primary_label',
                'required' => false,
                'help' => 'glossary_config.primary_label_help',
            ])
            ->add('definitionLabel', TextType::class, [
                'label' => 'glossary_config.definition_label',
                'required' => false,
                'help' => 'glossary_config.definition_label_help',
            ])
            ->add('secondaryEnabled', CheckboxType::class, [
                'label' => 'glossary_config.secondary_enabled',
                'required' => false,
            ])
            ->add('secondaryLabel', TextType::class, [
                'label' => 'glossary_config.secondary_label',
                'required' => false,
                'help' => 'glossary_config.secondary_label_help',
            ])
            ->add('termLanguage', TextType::class, [
                'label' => 'glossary_config.term_language',
                'required' => false,
                'help' => 'glossary_config.term_language_help',
                'attr' => ['maxlength' => 5],
            ])
            ->add('trainerEnabled', CheckboxType::class, [
                'label' => 'glossary_config.trainer_enabled',
                'required' => false,
                'help' => 'glossary_config.trainer_enabled_help',
            ])
            ->add('sessionSize', IntegerType::class, [
                'label' => 'glossary_config.session_size',
                'empty_data' => '20',
                'attr' => ['min' => Config::SESSION_SIZE_MIN, 'max' => Config::SESSION_SIZE_MAX],
            ])
            ->add('newCardsPerDay', IntegerType::class, [
                'label' => 'glossary_config.new_cards_per_day',
                'help' => 'glossary_config.new_cards_per_day_help',
                'empty_data' => '10',
                'attr' => ['min' => 0, 'max' => Config::NEW_CARDS_MAX],
            ])
            ->add('directions', EnumType::class, [
                'class' => Direction::class,
                'label' => 'glossary_config.directions',
                'help' => 'glossary_config.directions_help',
                'multiple' => true,
                'expanded' => true,
                'choice_label' => static fn(Direction $direction): string => $direction->label(),
            ])
            ->add('defaultAnswerMode', EnumType::class, [
                'class' => AnswerMode::class,
                'label' => 'glossary_config.default_answer_mode',
                'choice_label' => static fn(AnswerMode $mode): string => $mode->label(),
            ])
            ->add('leaderboardEnabled', CheckboxType::class, [
                'label' => 'glossary_config.leaderboard_enabled',
                'required' => false,
                'help' => 'glossary_config.leaderboard_enabled_help',
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
        return 'glossary_config';
    }
}
