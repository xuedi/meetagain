<?php declare(strict_types=1);

namespace Plugin\Dinnerclub\Form;

use Plugin\Dinnerclub\Entity\DishList;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

class DishListType extends AbstractType
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {}

    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('name', TextType::class, [
            'label' => 'dinnerclub_lists.field_name',
            'required' => true,
            'attr' => ['placeholder' => $this->translator->trans('dinnerclub_lists.field_name_placeholder')],
        ])->add('description', TextareaType::class, [
            'label' => 'dinnerclub_lists.field_description',
            'required' => false,
            'attr' => [
                'rows' => 3,
                'placeholder' => $this->translator->trans('dinnerclub_lists.field_description_placeholder'),
            ],
        ])->add('isPublic', CheckboxType::class, [
            'label' => 'dinnerclub_lists.field_is_public',
            'required' => false,
            'help' => 'dinnerclub_lists.field_is_public_help',
        ]);
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => DishList::class,
        ]);
    }
}
