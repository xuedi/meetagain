<?php declare(strict_types=1);

namespace App\Form;

use App\Entity\Host;
use App\Entity\User;
use Override;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class HostType extends AbstractType
{
    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'admin_host.form_label_name',
                'constraints' => [
                    new NotBlank(),
                    new Length(max: 16),
                ],
            ])
            ->add('user', EntityType::class, [
                'label' => 'admin_host.form_label_user',
                'class' => User::class,
                'choice_label' => 'name',
                'required' => false,
            ]);
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Host::class,
        ]);
    }
}
