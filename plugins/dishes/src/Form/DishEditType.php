<?php declare(strict_types=1);

namespace Plugin\Dishes\Form;

use App\Item\ItemTranslationFormHelper;
use Plugin\Dishes\Entity\Dish;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Edit form for a dish: the translatable name/description/recipe rendered once per enabled
 * language (via ItemTranslationFormHelper) plus the language-neutral phonetic, origin and
 * preview fields. The controller reads the per-language values back through the same helper.
 */
class DishEditType extends AbstractType
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly ItemTranslationFormHelper $translationFormHelper,
    ) {}

    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $dish = $options['dish'];

        $this->translationFormHelper->addTranslatedFields(
            $builder,
            [
                'name' => [TextType::class, ['label' => 'dishes_dish.field_name', 'required' => false]],
                'description' => [TextareaType::class, ['label' => 'dishes_dish.field_description', 'required' => false, 'attr' => ['rows' => 3]]],
                'recipe' => [TextareaType::class, ['label' => 'dishes_dish.field_recipe', 'required' => false, 'attr' => ['rows' => 5]]],
            ],
            static fn(string $code, string $field): ?string => match ($field) {
                'name' => $dish?->findTranslation($code)?->getName() ?? '',
                'description' => $dish?->findTranslation($code)?->getDescription(),
                'recipe' => $dish?->findTranslation($code)?->getRecipe(),
                default => null,
            },
        );

        $builder->add('phonetic', TextType::class, [
            'label' => 'dishes_dish.field_phonetic',
            'required' => false,
            'mapped' => false,
            'data' => $dish?->getPhonetic(),
        ])->add('origin', TextType::class, [
            'label' => 'dishes_dish.field_origin',
            'required' => false,
            'mapped' => false,
            'data' => $dish?->getOrigin(),
        ])->add('previewFile', FileType::class, [
            'label' => 'dishes_dish.field_preview',
            'required' => false,
            'mapped' => false,
            'constraints' => [
                new File(maxSize: '8000k', mimeTypes: ['image/*'], mimeTypesMessage: $this->translator->trans('dishes_dish.error_invalid_image')),
            ],
        ])->add('submit', SubmitType::class, [
            'label' => 'dishes_dish.button_save',
            'attr' => ['class' => 'button is-primary'],
        ]);
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefault('dish', null);
        $resolver->setAllowedTypes('dish', [Dish::class, 'null']);
    }
}
