<?php declare(strict_types=1);

namespace App\Form;

use App\Entity\Cms;
use Override;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

class CmsType extends AbstractType
{
    public function __construct(public readonly TranslatorInterface $translator)
    {
    }

    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('slug', TextType::class, [
            'label' => false, // TODO: add SLUG restrains
        ])->add('published', ChoiceType::class, [
            'label' => false,
            'choices' => [
                $this->translator->trans('Published') => 1,
                $this->translator->trans('Draft') => 0,
            ],
        ]);
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Cms::class,
        ]);
    }
}
