<?php declare(strict_types=1);

namespace Plugin\Boardgames\Form;

use Override;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SearchType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

class GameLookupType extends AbstractType
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {}

    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('query', SearchType::class, [
            'label' => $this->translator->trans('boardgames_game.label_search'),
            'required' => true,
            'attr' => ['placeholder' => $this->translator->trans('boardgames_game.label_search_placeholder')],
        ])->add('search', SubmitType::class, [
            'label' => $this->translator->trans('boardgames_game.button_search'),
            'attr' => ['class' => 'button'],
        ]);
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'method' => 'GET',
            'csrf_protection' => false,
        ]);
    }
}
