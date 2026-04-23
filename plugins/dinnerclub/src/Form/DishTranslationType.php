<?php declare(strict_types=1);

namespace Plugin\Dinnerclub\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

class DishTranslationType extends AbstractType
{
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('name', TextType::class, [
            'label' => 'dinnerclub.field_translation_name',
            'required' => true,
        ])->add('description', TextareaType::class, [
            'label' => 'dinnerclub.field_description',
            'required' => false,
            'attr' => ['rows' => 3],
        ])->add('recipe', TextareaType::class, [
            'label' => 'dinnerclub.field_recipe',
            'required' => false,
            'attr' => ['rows' => 5],
        ]);
    }
}
