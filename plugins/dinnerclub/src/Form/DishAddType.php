<?php declare(strict_types=1);

namespace Plugin\Dinnerclub\Form;

use App\Service\Config\LanguageService;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DishAddType extends AbstractType
{
    public function __construct(
        private readonly LanguageService $languageService,
    ) {}

    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $codes = $this->languageService->getEnabledCodes();
        $choices = array_combine(array_map('strtoupper', $codes), $codes);

        $builder->add('language', ChoiceType::class, [
            'label' => 'Language',
            'choices' => $choices,
            'data' => $options['current_locale'],
            'required' => true,
        ])->add('name', TextType::class, [
            'label' => 'Dish Name',
            'required' => true,
            'attr' => ['placeholder' => 'Enter dish name in your language'],
        ])->add('phonetic', TextType::class, [
            'label' => 'Phonetic',
            'required' => false,
            'attr' => ['placeholder' => 'Optional: How to pronounce it'],
        ])->add('description', TextareaType::class, [
            'label' => 'Description',
            'required' => false,
            'attr' => [
                'rows' => 3,
                'placeholder' => 'Optional: Brief description of the dish',
            ],
        ])->add('recipe', TextareaType::class, [
            'label' => 'Recipe',
            'required' => false,
            'attr' => [
                'rows' => 5,
                'placeholder' => 'Optional: How to prepare this dish',
            ],
        ])->add('origin', TextType::class, [
            'label' => 'Origin / Region',
            'required' => false,
            'attr' => ['placeholder' => 'Optional: Where is this dish from? (e.g., Southern China, Naples, NYC, Berlin)'],
        ]);
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['current_locale' => null]);
    }
}
