<?php declare(strict_types=1);

namespace App\Form;

use App\Entity\User;
use Override;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class RegistrationType extends AbstractType
{
    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('name', TextType::class, [
            'label' => 'security.label_username',
            'attr' => ['maxlength' => User::NAME_MAX_LENGTH],
            'constraints' => [
                new NotBlank(message: 'security.validator_username_blank'),
                new Length(max: User::NAME_MAX_LENGTH, maxMessage: 'security.validator_username_max'),
            ],
        ])->add('email', EmailType::class, [
            'label' => 'security.label_email',
            'constraints' => [
                new NotBlank(message: 'security.validator_email_blank'),
                new Email(message: 'security.validator_email_format'),
                new Length(max: 180, maxMessage: 'security.validator_email_max'),
            ],
        ])->add('agreeTerms', CheckboxType::class, [
            'label' => 'security.label_agree_terms',
            'mapped' => false,
            'constraints' => [
                new IsTrue(message: 'security.validator_agree_terms'),
            ],
        ])->add('plainPassword', PasswordType::class, [
            'label' => 'security.label_password',
            'mapped' => false,
            'attr' => ['autocomplete' => 'new-password'],
            'constraints' => [
                new NotBlank(message: 'security.validator_password_blank'),
                new Length(min: 6, max: 254, minMessage: 'security.validator_password_min'),
            ],
        ]);
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
