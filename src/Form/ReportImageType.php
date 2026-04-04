<?php declare(strict_types=1);

namespace App\Form;

use App\Enum\ImageReportReason;
use Override;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

class ReportImageType extends AbstractType
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {}

    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('reported', ChoiceType::class, [
                'label' => $this->translator->trans('report_image_reason'),
                'choices' => ImageReportReason::getChoices($this->translator),
            ])
            ->add('remarks', TextareaType::class, [
                'label' => $this->translator->trans('report_image_remarks'),
                'required' => false,
                'attr' => [
                    'rows' => 4,
                    'placeholder' => $this->translator->trans('report_image_remarks_placeholder'),
                ],
            ]);
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}
