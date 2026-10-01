<?php declare(strict_types=1);

namespace App\Form;

use Module\Ballot\Contract\TallyMode;
use Override;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

final class BallotTermsType extends AbstractType
{
    public const string FIELD_DURATION = 'durationDays';
    public const string FIELD_MODE = 'tallyMode';

    public const int DEFAULT_DURATION_DAYS = 7;
    private const int MAXIMUM_DURATION_DAYS = 90;

    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {}

    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $fields = $options['fields'];

        if (in_array(self::FIELD_DURATION, $fields, true)) {
            $builder->add(self::FIELD_DURATION, IntegerType::class, [
                'label' => $this->translator->trans('ballot_terms.label_duration'),
                'required' => false,
                'data' => self::DEFAULT_DURATION_DAYS,
                'attr' => ['min' => 1, 'max' => self::MAXIMUM_DURATION_DAYS],
            ]);
        }

        if (in_array(self::FIELD_MODE, $fields, true)) {
            $builder->add(self::FIELD_MODE, ChoiceType::class, [
                'label' => $this->translator->trans('ballot_terms.label_mode'),
                'required' => false,
                'expanded' => true,
                'placeholder' => false,
                'data' => $options['mode']->value,
                'choices' => [
                    $this->translator->trans('ballot_terms.mode_approval') => TallyMode::Approval->value,
                    $this->translator->trans('ballot_terms.mode_single') => TallyMode::Single->value,
                ],
            ]);
        }
    }

    #[Override]
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars['notice'] = $options['notice'];
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'mapped' => false,
            'label' => false,
            'fields' => [self::FIELD_DURATION, self::FIELD_MODE],
            'notice' => null,
            'mode' => TallyMode::Approval,
        ]);
        $resolver->setAllowedTypes('fields', 'array');
        $resolver->setAllowedTypes('notice', ['null', 'string']);
        $resolver->setAllowedTypes('mode', TallyMode::class);
    }

    /**
     * @param  array<string, mixed> $terms
     * @return array{deadline: string, tallyMode: TallyMode}
     */
    public function read(array $terms, TallyMode $default = TallyMode::Approval): array
    {
        $days = (int) ($terms[self::FIELD_DURATION] ?? self::DEFAULT_DURATION_DAYS);
        $days = max(1, min(self::MAXIMUM_DURATION_DAYS, $days));

        return [
            'deadline' => '+' . $days . ' days',
            'tallyMode' => TallyMode::tryFrom((string) ($terms[self::FIELD_MODE] ?? '')) ?? $default,
        ];
    }
}
