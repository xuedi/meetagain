<?php declare(strict_types=1);

namespace Plugin\Dishes\Form;

use App\Service\TranslationService;
use Plugin\Dishes\Entity\Dish;
use Plugin\Dishes\Repository\DishTranslationRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DishType extends AbstractType
{
    public function __construct(
        private readonly TranslationService $translationService,
        private readonly DishTranslationRepository $dishTransRepo,
    ) {
    }

    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $dish = $options['data'] ?? null;
        $dishId = $dish?->getId();
        foreach ($this->translationService->getLanguageCodes() as $languageCode) {
            $translation = $dishId !== null ? $this->dishTransRepo->findOneBy(['dish' => $dishId, 'language' => $languageCode]) : null;
            $builder->add("name-$languageCode", TextType::class, [
                'label' => "Name ($languageCode)",
                'data' => $translation?->getName() ?? '',
                'mapped' => false,
            ]);
            $builder->add("phonetic-$languageCode", TextType::class, [
                'label' => "Phonetic ($languageCode)",
                'data' => $translation?->getPhonetic() ?? '',
                'mapped' => false,
                'required' => false,
            ]);
            $builder->add("description-$languageCode", TextareaType::class, [
                'label' => "Description ($languageCode)",
                'data' => $translation?->getDescription() ?? '',
                'mapped' => false,
                'required' => false,
                'attr' => ['rows' => 5],
            ]);
        }
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Dish::class,
        ]);
    }
}
