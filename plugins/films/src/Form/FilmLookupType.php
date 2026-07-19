<?php declare(strict_types=1);

namespace Plugin\Films\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SearchType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

class FilmLookupType extends AbstractType
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {}

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('query', SearchType::class, [
            'label' => $this->translator->trans('films_film.label_search'),
            'required' => true,
            'attr' => ['placeholder' => $this->translator->trans('films_film.label_search_placeholder')],
        ])->add('year', IntegerType::class, [
            'label' => $this->translator->trans('films_film.label_year'),
            'required' => false,
            'attr' => ['placeholder' => 'e.g. 2024'],
        ])->add('search', SubmitType::class, [
            'label' => $this->translator->trans('films_film.button_search'),
            'attr' => ['class' => 'button'],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'method' => 'GET',
            'csrf_protection' => false,
        ]);
    }
}
