<?php declare(strict_types=1);

namespace Plugin\Dishes\Form;

use App\Service\Config\LanguageService;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Contracts\Translation\TranslatorInterface;

class DishAddType extends AbstractType
{
    public function __construct(
        private readonly LanguageService $languageService,
        private readonly TranslatorInterface $translator,
    ) {}

    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $codes = $this->languageService->getFilteredEnabledCodes();
        $choices = array_combine(array_map('strtoupper', $codes), $codes);

        $builder
            ->add('language', ChoiceType::class, [
                'label' => 'dishes_dish.field_language',
                'choices' => $choices,
                'data' => $options['current_locale'],
                'required' => true,
            ])
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
                'label' => 'dishes_dish.button_submit',
                'attr' => ['class' => 'button is-primary'],
            ]);
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['current_locale' => null]);
    }
}
