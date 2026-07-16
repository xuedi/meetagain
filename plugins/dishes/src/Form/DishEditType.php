<?php declare(strict_types=1);

namespace Plugin\Dishes\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Flat, unmapped edit form for the current-locale translation plus the language-neutral dish
 * fields. The controller reads the values and routes them to the dish and its translation.
 */
class DishEditType extends AbstractType
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {}

    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'dishes_dish.field_name',
                'required' => true,
            ])
            ->add('phonetic', TextType::class, [
                'label' => 'dishes_dish.field_phonetic',
                'required' => false,
            ])
            ->add('description', TextareaType::class, [
                'label' => 'dishes_dish.field_description',
                'required' => false,
                'attr' => ['rows' => 3],
            ])
            ->add('recipe', TextareaType::class, [
                'label' => 'dishes_dish.field_recipe',
                'required' => false,
                'attr' => ['rows' => 5],
            ])
            ->add('origin', TextType::class, [
                'label' => 'dishes_dish.field_origin',
                'required' => false,
            ])
            ->add('previewFile', FileType::class, [
                'label' => 'dishes_dish.field_preview',
                'required' => false,
                'mapped' => false,
                'constraints' => [
                    new File(maxSize: '8000k', mimeTypes: ['image/*'], mimeTypesMessage: $this->translator->trans('dishes_dish.error_invalid_image')),
                ],
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'dishes_dish.button_save',
                'attr' => ['class' => 'button is-primary'],
            ]);
    }
}
