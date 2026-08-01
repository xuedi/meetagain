<?php declare(strict_types=1);

namespace App\Form\Item;

use App\Service\Config\LanguageService;
use Override;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TagRowType extends AbstractType
{
    public function __construct(
        private readonly LanguageService $languageService,
    ) {}

    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('id', HiddenType::class, ['required' => false]);

        $builder->add('parent', ChoiceType::class, [
            'label' => false,
            'required' => false,
            'placeholder' => 'item.tag_parent_none',
            'choices' => $options['parent_choices'],
            'attr' => ['data-item-tag-parent-select' => ''],
        ]);

        foreach ($this->languageService->getAdminFilteredEnabledCodes() as $code) {
            $builder->add($code, TextType::class, [
                'label' => false,
                'required' => false,
                'property_path' => sprintf('[labels][%s]', $code),
            ]);
        }
    }

    #[Override]
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars['usage'] = $options['usage'];
        $view->vars['depths'] = $options['depths'];
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'empty_data' => ['id' => '', 'parent' => null, 'labels' => []],
            'usage' => [],
            'depths' => [],
            'parent_choices' => [],
        ]);
        $resolver->setAllowedTypes('usage', 'array');
        $resolver->setAllowedTypes('depths', 'array');
        $resolver->setAllowedTypes('parent_choices', 'array');
    }

    #[Override]
    public function getBlockPrefix(): string
    {
        return 'item_tag_row';
    }
}
