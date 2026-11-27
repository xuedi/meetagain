<?php declare(strict_types=1);

namespace App\Form;

use App\Entity\AccountDeletionFeedback;
use App\Enum\AccountDeletionReason;
use Override;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;

class AccountDeletionType extends AbstractType
{
    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('reason', EnumType::class, [
            'class' => AccountDeletionReason::class,
            'choice_label' => static fn(AccountDeletionReason $reason): string => $reason->label(),
            'label' => 'profile_config.form_label_delete_reason',
            'data' => AccountDeletionReason::RatherNotSay,
            'attr' => ['class' => 'is-fullwidth'],
            'constraints' => [new NotNull()],
        ])->add('comment', TextareaType::class, [
            'label' => 'profile_config.form_label_delete_comment',
            'help' => 'profile_config.form_help_delete_comment',
            'required' => false,
            'attr' => [
                'rows' => 5,
                'maxlength' => AccountDeletionFeedback::MAX_COMMENT_LENGTH,
                'placeholder' => 'profile_config.placeholder_delete_comment',
            ],
            'constraints' => [
                new Length(max: AccountDeletionFeedback::MAX_COMMENT_LENGTH, maxMessage: 'profile_config.validator_delete_comment_long'),
            ],
        ])->add('password', PasswordType::class, [
            'label' => 'profile_config.form_label_password',
            'attr' => ['autocomplete' => 'current-password'],
            'constraints' => [new NotBlank(message: 'profile_config.validator_delete_password_blank')],
        ])->add('acknowledge', CheckboxType::class, [
            'label' => 'profile_config.form_label_delete_acknowledge',
            'label_translation_parameters' => ['%days%' => $options['erasure_window_days']],
            'constraints' => [new IsTrue(message: 'profile_config.validator_delete_acknowledge_missing')],
        ]);
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => null]);
        $resolver->setRequired('erasure_window_days');
        $resolver->setAllowedTypes('erasure_window_days', 'int');
    }
}
