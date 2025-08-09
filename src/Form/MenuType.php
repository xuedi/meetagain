<?php declare(strict_types=1);

namespace App\Form;

use App\Entity\Menu;
use App\Entity\MenuLocation;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

class MenuType extends AbstractType
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('slug', TextType::class, [
                'required' => true
            ])
            ->add('location', ChoiceType::class, [
                'mapped' => false,
                'label' => 'Language after login:',
                'choices' => MenuLocation::getChoices($this->translator),
            ])
            ->add(
                'visibility',
                ChoiceType::class,
                [
                    'choices' => [
                        $this->translator->trans('role_system') => 'ROLE_SYSTEM',
                        $this->translator->trans('role_admin') => 'ROLE_ADMIN',
                        $this->translator->trans('role_manager') => 'ROLE_MANAGER',
                        $this->translator->trans('role_user') => 'ROLE_USER',
                    ],
                    'expanded' => true,
                    'multiple' => true,
                ]
            );
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Menu::class,
        ]);
    }
}
