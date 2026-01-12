<?php declare(strict_types=1);

namespace Plugin\Dishes\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

class DishTranslationType extends AbstractType
{
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Name',
                'required' => true,
            ])
            ->add('phonetic', TextType::class, [
                'label' => 'Phonetic / Pronunciation',
                'required' => false,
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => ['rows' => 3],
            ])
            ->add('recipe', TextareaType::class, [
                'label' => 'Recipe',
                'required' => false,
                'attr' => ['rows' => 5],
            ]);
    }
}
