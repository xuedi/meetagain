<?php declare(strict_types=1);

namespace Plugin\Dishes\Form;

use App\Service\Config\LanguageService;
use Plugin\Dishes\ValueObject\Config;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ConfigType extends AbstractType
{
    public function __construct(
        private readonly LanguageService $languageService,
    ) {}

    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('phoneticInList', CheckboxType::class, [
            'label' => 'dishes_config.field_phonetic_in_list',
            'help' => 'dishes_config.help_phonetic_in_list',
            'required' => false,
        ]);

        foreach ($this->languageService->getAdminFilteredEnabledCodes() as $code) {
            $builder->add($code, TextareaType::class, [
                'label' => strtoupper($code),
                'property_path' => sprintf('footerText[%s]', $code),
                'required' => false,
                'attr' => ['rows' => 3],
            ]);
        }
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Config::class,
        ]);
    }

    #[\Override]
    public function getBlockPrefix(): string
    {
        return 'dishes_config';
    }
}
