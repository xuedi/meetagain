<?php declare(strict_types=1);

namespace App\Form;

use Override;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;

class PasswordResetType extends AbstractType
{
    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('email', EmailType::class, [
            'constraints' => [
                new NotBlank(message: 'security.validator_email_blank'),
                new Email(message: 'security.validator_email_format'),
            ],
        ])->add('meta', HumanCheckType::class, [
            'context' => 'app_reset',
        ]);
    }
}
