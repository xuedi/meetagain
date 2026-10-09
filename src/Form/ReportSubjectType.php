<?php declare(strict_types=1);

namespace App\Form;

use App\Entity\ModerationReport;
use App\Enum\ModerationReportReason;
use Override;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotNull;

class ReportSubjectType extends AbstractType
{
    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('reason', EnumType::class, [
            'class' => ModerationReportReason::class,
            'choice_label' => static fn(ModerationReportReason $reason): string => $reason->label(),
            'expanded' => true,
            'label' => 'report.label_reason',
            'data' => ModerationReportReason::Spam,
            'constraints' => [new NotNull()],
        ])->add('remarks', TextareaType::class, [
            'label' => 'report.label_remarks',
            'required' => false,
            'attr' => [
                'rows' => 4,
                'placeholder' => 'report.placeholder_remarks',
            ],
            'constraints' => [
                new Length(max: ModerationReport::MAX_REMARKS_LENGTH, maxMessage: 'report.validator_remarks_long'),
            ],
        ]);
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => null]);
    }
}
