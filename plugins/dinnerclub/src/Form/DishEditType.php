<?php declare(strict_types=1);

namespace Plugin\Dinnerclub\Form;

use App\Service\Config\LanguageService;
use Plugin\Dinnerclub\Entity\Dish;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
class DishEditType extends AbstractType
{
    public function __construct(
        private readonly LanguageService $languageService,
    ) {}

    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $codes = $this->languageService->getFilteredEnabledCodes();
        $choices = array_combine(array_map('strtoupper', $codes), $codes);

        $builder
            ->add('originLang', ChoiceType::class, [
                'label' => 'Origin Language',
                'choices' => $choices,
                'required' => true,
            ])
            ->add('origin', TextType::class, [
                'label' => 'Origin / Region',
                'required' => false,
                'attr' => ['placeholder' => 'Optional: Where is this dish from? (e.g., Southern China, Naples, NYC, Berlin)'],
            ]);
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Dish::class,
        ]);
    }
}
