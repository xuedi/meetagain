<?php declare(strict_types=1);

namespace Plugin\Filmclub\Form;

use Plugin\Filmclub\Entity\Film;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
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
        $builder->add('films', EntityType::class, [
            'class' => Film::class,
            'choices' => $options['available_films'],
            'choice_label' => static fn(Film $f) => $f->getTitle() ?? '?',
            'multiple' => true,
            'expanded' => true,
            'label' => $this->translator->trans('filmclub_poll.label_films'),
            'required' => true,
        ])->add('durationDays', IntegerType::class, [
            'label' => $this->translator->trans('filmclub_poll.label_duration_days'),
            'help' => $this->translator->trans('filmclub_poll.help_duration_days'),
            'data' => 7,
            'attr' => ['min' => 1, 'max' => 365],
            'required' => true,
        ])->add('submit', SubmitType::class, [
            'label' => $this->translator->trans('filmclub_poll.button_create'),
            'attr' => ['class' => 'button is-primary'],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'available_films' => [],
        ]);
    }
}
