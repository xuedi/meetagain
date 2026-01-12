<?php declare(strict_types=1);

namespace Plugin\Dishes\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

class DishAddType extends AbstractType
{
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Dish Name',
                'required' => true,
                'attr' => ['placeholder' => 'Enter dish name in your language'],
            ])
            ->add('phonetic', TextType::class, [
                'label' => 'Phonetic / Pronunciation',
                'required' => false,
                'attr' => ['placeholder' => 'Optional: How to pronounce it'],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => [
                    'rows' => 3,
                    'placeholder' => 'Optional: Brief description of the dish',
                ],
            ])
            ->add('recipe', TextareaType::class, [
                'label' => 'Recipe',
                'required' => false,
                'attr' => [
                    'rows' => 5,
                    'placeholder' => 'Optional: How to prepare this dish',
                ],
            ])
            ->add('origin', TextType::class, [
                'label' => 'Origin / Region',
                'required' => false,
                'attr' => ['placeholder' => 'Optional: Where is this dish from? (e.g., Sichuan, Southern China)'],
            ]);
    }
}
