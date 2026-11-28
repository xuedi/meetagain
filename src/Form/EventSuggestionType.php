<?php declare(strict_types=1);

namespace App\Form;

use Override;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class EventSuggestionType extends AbstractType
{
    public const int REASON_MAX = 2000;

    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('title', TextType::class, [
            'label' => 'events.suggest_label_title',
            'constraints' => [new NotBlank(message: 'events.validator_suggest_title_blank')],
        ])->add('teaser', TextareaType::class, [
            'label' => 'events.suggest_label_teaser',
            'required' => false,
            'attr' => ['rows' => 3],
        ])->add('description', TextareaType::class, [
            'label' => 'events.suggest_label_description',
            'required' => false,
            'attr' => ['rows' => 12],
        ])->add('reason', TextareaType::class, [
            'label' => 'events.suggest_label_reason',
            'help' => 'events.suggest_help_reason',
            'attr' => ['rows' => 4],
            'constraints' => [
                new NotBlank(message: 'events.validator_suggest_reason_blank'),
                new Length(max: self::REASON_MAX, maxMessage: 'events.validator_suggest_reason_long'),
            ],
        ]);

        if ($options['series']) {
            $builder->add('series', CheckboxType::class, [
                'label' => 'events.suggest_label_series',
                'help' => 'events.suggest_help_series',
                'required' => false,
            ]);
        }
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => null, 'series' => false]);
        $resolver->setAllowedTypes('series', 'bool');
    }
}
