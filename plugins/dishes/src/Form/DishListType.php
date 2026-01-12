<?php declare(strict_types=1);

namespace Plugin\Dishes\Form;

use Plugin\Dishes\Entity\DishList;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DishListType extends AbstractType
{
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'List Name',
                'required' => true,
                'attr' => ['placeholder' => 'e.g., My Favorite Sichuan Dishes'],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => [
                    'rows' => 3,
                    'placeholder' => 'Optional: Describe what this list is about',
                ],
            ])
            ->add('isPublic', CheckboxType::class, [
                'label' => 'Make this list public',
                'required' => false,
                'help' => 'Public lists can be viewed by other users',
            ]);
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => DishList::class,
        ]);
    }
}
