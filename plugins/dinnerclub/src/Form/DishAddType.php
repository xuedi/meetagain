<?php declare(strict_types=1);

namespace Plugin\Dinnerclub\Form;

use App\Service\Config\LanguageService;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
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

        $builder->add('language', ChoiceType::class, [
            'label' => 'dinnerclub.field_language',
            'choices' => $choices,
            'data' => $options['current_locale'],
            'required' => true,
        ])->add('name', TextType::class, [
            'label' => 'dinnerclub.field_name',
            'required' => true,
            'attr' => ['placeholder' => $this->translator->trans('dinnerclub.field_name_placeholder')],
        ])->add('phonetic', TextType::class, [
            'label' => 'dinnerclub.field_phonetic',
            'required' => false,
            'attr' => ['placeholder' => $this->translator->trans('dinnerclub.field_phonetic_placeholder')],
        ])->add('description', TextareaType::class, [
            'label' => 'dinnerclub.field_description',
            'required' => false,
            'attr' => [
                'rows' => 3,
                'placeholder' => $this->translator->trans('dinnerclub.field_description_placeholder'),
            ],
        ])->add('recipe', TextareaType::class, [
            'label' => 'dinnerclub.field_recipe',
            'required' => false,
            'attr' => [
                'rows' => 5,
                'placeholder' => $this->translator->trans('dinnerclub.field_recipe_placeholder'),
            ],
        ])->add('origin', TextType::class, [
            'label' => 'dinnerclub.field_origin',
            'required' => false,
            'attr' => ['placeholder' => $this->translator->trans('dinnerclub.field_origin_placeholder')],
        ]);
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['current_locale' => null]);
    }
}
