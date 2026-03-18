<?php declare(strict_types=1);

namespace App\Form;

use App\Enum\EventRsvpFilter;
use App\Enum\EventSortFilter;
use App\Enum\EventTimeFilter;
use App\Enum\EventType;
use Override;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

class EventFilterType extends AbstractType
{
    public function __construct(
        public readonly TranslatorInterface $translator,
        #[AutowireIterator(EventFilterFormContributorInterface::class)]
        private readonly iterable $contributors = [],
    ) {}

    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('time', ChoiceType::class, [
            'data' => EventTimeFilter::Future,
            'label' => false,
            'choices' => [
                $this->translator->trans('event_filter_time_all') => EventTimeFilter::All,
                $this->translator->trans('event_filter_time_past') => EventTimeFilter::Past,
                $this->translator->trans('event_filter_time_future') => EventTimeFilter::Future,
            ],
        ])->add('sort', ChoiceType::class, [
            'data' => EventSortFilter::OldToNew,
            'label' => false,
            'choices' => [
                $this->translator->trans('event_filter_sort_past') => EventSortFilter::OldToNew,
                $this->translator->trans('event_filter_sort_future') => EventSortFilter::NewToOld,
            ],
        ])->add('type', ChoiceType::class, [
            'data' => EventType::All,
            'label' => false,
            'choices' => [
                $this->translator->trans('event_filter_type_all') => EventType::All,
                $this->translator->trans('event_filter_type_regular') => EventType::Regular,
                $this->translator->trans('event_filter_type_outdoor') => EventType::Outdoor,
                $this->translator->trans('event_filter_type_dinner') => EventType::Dinner,
            ],
        ])->add('rsvp', ChoiceType::class, [
            'data' => EventRsvpFilter::All,
            'label' => false,
            'choices' => [
                $this->translator->trans('event_filter_who_all') => EventRsvpFilter::All,
                $this->translator->trans('event_filter_who_my') => EventRsvpFilter::My,
                $this->translator->trans('event_filter_who_my_friends') => EventRsvpFilter::Friends,
            ],
        ]);

        foreach ($this->contributors as $contributor) {
            $contributor->addFields($builder);
        }
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'csrf_protection' => false,
            'method' => 'GET',
        ]);
    }

    #[Override]
    public function getBlockPrefix(): string
    {
        return '';
    }
}
