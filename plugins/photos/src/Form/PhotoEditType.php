<?php declare(strict_types=1);

namespace Plugin\Photos\Form;

use App\Filter\Event\EventFilterService;
use App\Item\Tag\AssignmentFormHelper;
use App\Item\TranslationFormHelper;
use App\Repository\EventRepository;
use App\Service\Item\AssociationService;
use Override;
use Plugin\Photos\Entity\Photo;
use Plugin\Photos\Service\PhotoService;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PhotoEditType extends AbstractType
{
    public const string EVENT_FIELD = 'event';

    private const int EVENT_CHOICE_LIMIT = 200;

    public function __construct(
        private readonly TranslationFormHelper $translationFormHelper,
        private readonly AssignmentFormHelper $assignmentFormHelper,
        private readonly EventRepository $eventRepository,
        private readonly EventFilterService $eventFilterService,
        private readonly AssociationService $associations,
        private readonly RequestStack $requestStack,
    ) {}

    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $photo = $options['photo'];

        $this->translationFormHelper->addTranslatedFields(
            $builder,
            [
                'title' => [TextType::class, ['label' => 'photos_photo.field_title', 'required' => false]],
                'description' => [TextareaType::class, ['label' => 'photos_photo.field_description', 'required' => false, 'attr' => ['rows' => 3]]],
            ],
            static fn(string $code, string $field): ?string => match ($field) {
                'title' => $photo?->findTranslation($code)?->getTitle() ?? '',
                'description' => $photo?->findTranslation($code)?->getDescription(),
                default => null,
            },
        );

        $builder->add(self::EVENT_FIELD, ChoiceType::class, [
            'label' => 'photos_photo.field_event',
            'help' => 'photos_photo.help_event',
            'choices' => $this->eventChoices(),
            'placeholder' => 'photos_photo.choice_no_event',
            'required' => false,
            'mapped' => false,
            'data' => $this->currentEventId($photo),
        ]);

        $this->assignmentFormHelper->addAssignmentFields($builder, PhotoService::ITEM_TYPE, $photo?->getId());

        $builder->add('submit', SubmitType::class, [
            'label' => 'photos_photo.button_save',
            'attr' => ['class' => 'button is-primary'],
        ]);
    }

    /** @return array<string, int> */
    private function eventChoices(): array
    {
        return $this->eventRepository->getOccurrenceChoices(
            $this->requestStack->getCurrentRequest()?->getLocale() ?? 'en',
            $this->eventFilterService->getEventIdFilter()->getEventIds(),
            self::EVENT_CHOICE_LIMIT,
        );
    }

    private function currentEventId(?Photo $photo): ?int
    {
        $photoId = $photo?->getId();

        return $photoId === null ? null : ($this->associations->eventIdsForItem(PhotoService::ITEM_TYPE, $photoId)[0] ?? null);
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefault('photo', null);
        $resolver->setAllowedTypes('photo', [Photo::class, 'null']);
    }
}
