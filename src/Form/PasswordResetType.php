<?php declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;

class PasswordResetType extends AbstractType
{
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('email', EmailType::class, [
            'constraints' => [
                new NotBlank(message: 'Please enter your email address.'),
                new Email(message: 'Please enter a valid email address.'),
            ],
        ])->add('captcha', TextType::class, [
            'mapped' => false,
            'label' => 'Enter captcha code',
            'constraints' => [
                new NotBlank(),
            ],
        ]);
    }
}
