<?php declare(strict_types=1);

namespace Plugin\Glossary\Form;

use Override;
use Plugin\Glossary\Enum\AnswerMode;
use Plugin\Glossary\Enum\Direction;
use Plugin\Glossary\Enum\Mode;
use Plugin\Glossary\Enum\Scope;
use Plugin\Glossary\ValueObject\Config;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

class TrainerSetupType extends AbstractType
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {}

    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('scope', EnumType::class, [
                'class' => Scope::class,
                'label' => 'glossary_trainer.field_scope',
                'choice_label' => static fn(Scope $scope): string => $scope->label(),
            ])
            ->add('tags', HiddenType::class, [
                'required' => false,
            ])
            ->add('mode', EnumType::class, [
                'class' => Mode::class,
                'label' => 'glossary_trainer.field_mode',
                'expanded' => true,
                'choice_label' => static fn(Mode $mode): string => $mode->label(),
                'help' => 'glossary_trainer.field_mode_help',
            ])
            ->add('direction', EnumType::class, [
                'class' => Direction::class,
                'label' => 'glossary_trainer.field_direction',
                'choices' => $options['directions'],
                'choice_label' => static fn(Direction $direction): string => $direction->label(),
            ])
            ->add('answerMode', EnumType::class, [
                'class' => AnswerMode::class,
                'label' => 'glossary_trainer.field_answer_mode',
                'expanded' => true,
                'choice_label' => static fn(AnswerMode $mode): string => $mode->label(),
            ])
            ->add('size', IntegerType::class, [
                'label' => 'glossary_trainer.field_size',
                'attr' => ['min' => Config::SESSION_SIZE_MIN, 'max' => Config::SESSION_SIZE_MAX],
            ]);

        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event): void {
            $data = $event->getData();
            $typing = ($data['answerMode'] ?? null) === AnswerMode::Typing;
            $direction = $data['direction'] ?? null;
            if ($typing && $direction instanceof Direction && !$direction->answersWithTerm()) {
                $event
                    ->getForm()
                    ->get('answerMode')
                    ->addError(new FormError($this->translator->trans('glossary_trainer.error_typing_needs_term')));
            }
        });
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired('directions');
        $resolver->setAllowedTypes('directions', 'array');
        $resolver->setDefaults([
            'csrf_token_id' => 'glossary_trainer_setup',
        ]);
    }

    #[Override]
    public function getBlockPrefix(): string
    {
        return 'trainer_setup';
    }
}
