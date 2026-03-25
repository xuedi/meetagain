<?php declare(strict_types=1);

namespace App\Form;

use App\Enum\ContactType;
use Override;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class SupportRequestType extends AbstractType
{
    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $choices = [];
        foreach (ContactType::cases() as $case) {
            $choices[$case->label()] = $case;
        }

        $builder->add('contactType', ChoiceType::class, [
            'choices' => $choices,
            'label' => 'Contact type',
            'constraints' => [new NotBlank()],
        ])->add('name', TextType::class, [
            'constraints' => [
                new NotBlank(message: 'Please enter your name.'),
                new Length(max: 100, maxMessage: 'Name cannot be longer than {{ limit }} characters.'),
            ],
        ])->add('email', EmailType::class, [
            'constraints' => [
                new NotBlank(message: 'Please enter your email address.'),
                new Email(message: 'Please enter a valid email address.'),
            ],
        ])->add('message', TextareaType::class, [
            'constraints' => [
                new NotBlank(message: 'Please enter a message.'),
                new Length(max: 2000, maxMessage: 'Message cannot be longer than {{ limit }} characters.'),
            ],
        ])->add('captcha', TextType::class, [
            'mapped' => false,
            'label' => 'Enter captcha code',
            'required' => false,
        ]);
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
        ]);
    }
}
