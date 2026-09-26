<?php declare(strict_types=1);

namespace App\Form;

use App\Enum\ItemReportReason;
use App\Enum\ItemReportRelationship;
use Override;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;

class ItemReportType extends AbstractType
{
    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isMember = $options['member_name'] !== null;

        $builder
            ->add('reason', EnumType::class, [
                'class' => ItemReportReason::class,
                'choice_label' => static fn(ItemReportReason $reason): string => $reason->label(),
                'expanded' => true,
                'label' => 'item_report.label_reason',
                'data' => ItemReportReason::Copyright,
                'constraints' => [new NotNull()],
            ])
            ->add('relationship', EnumType::class, [
                'class' => ItemReportRelationship::class,
                'choice_label' => static fn(ItemReportRelationship $relationship): string => $relationship->label(),
                'expanded' => true,
                'label' => 'item_report.label_relationship',
                'data' => ItemReportRelationship::Other,
                'constraints' => [new NotNull()],
            ])
            ->add('explanation', TextareaType::class, [
                'label' => 'item_report.label_explanation',
                'help' => 'item_report.help_explanation',
                'attr' => ['rows' => 6],
                'constraints' => [
                    new NotBlank(message: 'item_report.validator_explanation_blank'),
                    new Length(min: 10, max: 5000, minMessage: 'item_report.validator_explanation_short', maxMessage: 'item_report.validator_explanation_long'),
                ],
            ])
            ->add('name', TextType::class, [
                'label' => 'item_report.label_name',
                'data' => $options['member_name'],
                'disabled' => $isMember,
                'constraints' => $isMember ? [] : [new NotBlank(), new Length(max: 255)],
            ])
            ->add('email', EmailType::class, [
                'label' => 'item_report.label_email',
                'help' => 'item_report.help_email',
                'data' => $options['member_email'],
                'disabled' => $isMember,
                'constraints' => $isMember ? [] : [new NotBlank(), new Email(), new Length(max: 180)],
            ])
            ->add('goodFaith', CheckboxType::class, [
                'label' => 'item_report.label_good_faith',
                'constraints' => [new IsTrue(message: 'item_report.validator_good_faith')],
            ]);

        if ($isMember) {
            return;
        }

        $builder->add('meta', HumanCheckType::class, [
            'context' => 'app_report_item',
        ]);
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'member_name' => null,
            'member_email' => null,
        ]);
        $resolver->setAllowedTypes('member_name', ['string', 'null']);
        $resolver->setAllowedTypes('member_email', ['string', 'null']);
    }
}
