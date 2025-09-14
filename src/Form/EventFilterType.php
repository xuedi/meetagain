<?php declare(strict_types=1);

namespace App\Form;

use App\Entity\EventFilterRsvp;
use App\Entity\EventFilterSort;
use App\Entity\EventFilterTime;
use App\Entity\EventTypes;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

class EventFilterType extends AbstractType
{
    public function __construct(
        readonly TranslatorInterface $translator,
    ) {}

    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('time', ChoiceType::class, [
                'data' => EventFilterTime::Future,
                'label' => false,
                'choices' => [
                    $this->translator->trans('event_filter_time_all') => EventFilterTime::All,
                    $this->translator->trans('event_filter_time_past') => EventFilterTime::Past,
                    $this->translator->trans('event_filter_time_future') => EventFilterTime::Future,
                ],
            ])
            ->add('sort', ChoiceType::class, [
                'data' => EventFilterSort::OldToNew,
                'label' => false,
                'choices' => [
                    $this->translator->trans('event_filter_sort_past') => EventFilterSort::OldToNew,
                    $this->translator->trans('event_filter_sort_future') => EventFilterSort::NewToOld,
                ],
            ])
            ->add('type', ChoiceType::class, [
                'data' => EventTypes::All,
                'label' => false,
                'choices' => [
                    $this->translator->trans('event_filter_type_all') => EventTypes::All,
                    $this->translator->trans('event_filter_type_regular') => EventTypes::Regular,
                    $this->translator->trans('event_filter_type_outdoor') => EventTypes::Outdoor,
                    $this->translator->trans('event_filter_type_dinner') => EventTypes::Dinner,
                ],
            ])
            ->add('rsvp', ChoiceType::class, [
                'data' => EventFilterRsvp::All,
                'label' => false,
                'choices' => [
                    $this->translator->trans('event_filter_who_all') => EventFilterRsvp::All,
                    $this->translator->trans('event_filter_who_my') => EventFilterRsvp::My,
                    $this->translator->trans('event_filter_who_my_friends') => EventFilterRsvp::Friends,
                ],
            ]);
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
        ]);
    }
}
