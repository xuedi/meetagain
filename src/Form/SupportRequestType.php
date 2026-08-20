<?php declare(strict_types=1);

namespace App\Form;

use App\Enum\SupportAudience;
use Override;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class SupportRequestType extends AbstractType
{
    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('audience', EnumType::class, [
            'class' => SupportAudience::class,
            'choice_label' => static fn(SupportAudience $audience): string => $audience->label(),
            'expanded' => true,
            'label' => 'support.form_label_audience',
            'constraints' => [new NotBlank()],
        ])->add('message', TextareaType::class, [
            'constraints' => [
                new NotBlank(message: 'support.validator_message_blank'),
                new Length(max: 2000, maxMessage: 'support.validator_message_max'),
            ],
        ]);

        if ($options['guest'] !== true) {
            return;
        }

        $builder->add('captcha', TextType::class, [
            'mapped' => false,
            'label' => 'support.form_label_captcha_input',
            'required' => false,
        ]);
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'guest' => true,
        ]);
        $resolver->setAllowedTypes('guest', 'bool');
    }
}
