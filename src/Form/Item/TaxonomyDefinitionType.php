<?php declare(strict_types=1);

namespace App\Form\Item;

use App\Service\Config\LanguageService;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TaxonomyDefinitionType extends AbstractType
{
    public function __construct(
        private readonly LanguageService $languageService,
    ) {}

    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('id', HiddenType::class, ['required' => false]);

        foreach ($this->languageService->getAdminFilteredEnabledCodes() as $code) {
            $builder->add($code, TextType::class, [
                'label' => false,
                'required' => false,
                'property_path' => sprintf('[labels][%s]', $code),
            ]);
        }
    }

    #[\Override]
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars['usage'] = $options['usage'];
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'empty_data' => ['id' => '', 'labels' => []],
            'usage' => [],
        ]);
        $resolver->setAllowedTypes('usage', 'array');
    }

    #[\Override]
    public function getBlockPrefix(): string
    {
        return 'taxonomy_definition_row';
    }
}
