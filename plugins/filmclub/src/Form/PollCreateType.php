<?php

declare(strict_types=1);

namespace Plugin\Filmclub\Form;

use Plugin\Filmclub\Entity\FilmSuggestion;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

class PollCreateType extends AbstractType
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {}

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('suggestions', EntityType::class, [
            'class' => FilmSuggestion::class,
            'choices' => $options['available_suggestions'],
            'choice_label' => static fn(FilmSuggestion $s) => $s->getFilm()?->getTitle() ?? '?',
            'multiple' => true,
            'expanded' => true,
            'label' => $this->translator->trans('filmclub_poll.label_suggestions'),
            'required' => true,
        ])->add('endDate', DateType::class, [
            'label' => $this->translator->trans('filmclub_poll.label_end_date'),
            'widget' => 'single_text',
            'data' => new \DateTime('+7 days'),
            'required' => true,
        ])->add('submit', SubmitType::class, [
            'label' => $this->translator->trans('filmclub_poll.button_create'),
            'attr' => ['class' => 'button is-primary'],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'available_suggestions' => [],
        ]);
    }
}
