<?php declare(strict_types=1);

namespace App\Form;

use App\Enum\EventType as EventTypeEnum;
use App\Event\Proposal;
use App\Filter\Admin\Location\AdminLocationListFilterService;
use App\Repository\LocationRepository;
use Override;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

class EventProposalType extends AbstractType
{
    public function __construct(
        private readonly LocationRepository $locationRepository,
        private readonly AdminLocationListFilterService $locationFilterService,
        private readonly TranslatorInterface $translator,
    ) {}

    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => $this->translator->trans('event_proposal.form_label_title'),
            ])
            ->add('start', DateTimeType::class, [
                'label' => $this->translator->trans('event_proposal.form_label_start'),
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('stop', DateTimeType::class, [
                'label' => $this->translator->trans('event_proposal.form_label_stop'),
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'required' => false,
            ])
            ->add('type', EnumType::class, [
                'class' => EventTypeEnum::class,
                'label' => $this->translator->trans('event_proposal.form_label_type'),
                'choices' => [EventTypeEnum::Regular, EventTypeEnum::Outdoor, EventTypeEnum::Dinner],
                'choice_label' => fn(EventTypeEnum $type): string => $this->translator->trans('event_proposal.type_' . strtolower($type->name)),
                'placeholder' => $this->translator->trans('event_proposal.form_placeholder_type'),
                'required' => false,
            ])
            ->add('location', ChoiceType::class, [
                'label' => $this->translator->trans('event_proposal.form_label_location'),
                'choices' => $this->locationChoices(),
                'choice_translation_domain' => false,
                'placeholder' => $this->translator->trans('event_proposal.form_placeholder_location'),
                'required' => false,
            ])
            ->add('teaser', TextType::class, [
                'label' => $this->translator->trans('event_proposal.form_label_teaser'),
                'required' => false,
                'empty_data' => '',
            ])
            ->add('description', TextareaType::class, [
                'label' => $this->translator->trans('event_proposal.form_label_description'),
                'empty_data' => '',
            ]);
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Proposal::class]);
    }

    /**
     * @return array<string, string>
     */
    private function locationChoices(): array
    {
        $choices = [];
        foreach ($this->locationRepository->findAllForAdmin($this->locationFilterService->getLocationIdFilter()->getLocationIds()) as $location) {
            $choices[(string) $location->getName()] = (string) $location->getId();
        }

        return $choices;
    }
}
