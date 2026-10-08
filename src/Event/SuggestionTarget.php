<?php declare(strict_types=1);

namespace App\Event;

use App\Activity\ActivityService;
use App\Activity\Messages\AdminEventCreated;
use App\Contribution\EventSection;
use App\Entity\Event;
use App\Entity\EventTranslation;
use App\Entity\Location;
use App\Entity\User;
use App\EntityActionDispatcher;
use App\Enum\EntityAction;
use App\Enum\EventStatus;
use App\Enum\EventType;
use App\Form\EventProposalType;
use App\Security\Permission\Attribute\PermissionAttribute;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Module\Suggestion\Contract\TargetProviderInterface;
use Override;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class SuggestionTarget implements TargetProviderInterface
{
    private const string DISPLAY_FORMAT = 'Y-m-d H:i';

    public function __construct(
        private EntityManagerInterface $em,
        private EntityActionDispatcher $entityActionDispatcher,
        private ActivityService $activityService,
        private Security $security,
        private RequestStack $requestStack,
        private TranslatorInterface $translator,
    ) {}

    #[Override]
    public function getPluginKey(): string
    {
        return '';
    }

    #[Override]
    public function getTargetType(): string
    {
        return EventSection::TYPE;
    }

    #[Override]
    public function getLabelKey(): string
    {
        return 'event_proposal.type_label';
    }

    #[Override]
    public function getFormType(): string
    {
        return EventProposalType::class;
    }

    #[Override]
    public function newDraft(): object
    {
        $draft = new Proposal();
        $draft->locale = $this->requestStack->getCurrentRequest()?->getLocale() ?? '';

        return $draft;
    }

    #[Override]
    public function fromPayload(array $payload): object
    {
        $draft = new Proposal();
        $draft->start = $this->date($payload, 'start');
        $draft->stop = $this->date($payload, 'stop');
        $draft->type = EventType::tryFrom((int) ($payload['type'] ?? 0));
        $draft->location = $this->optionalText($payload, 'location');
        $draft->locale = $this->text($payload, 'locale');
        $draft->title = $this->text($payload, 'title');
        $draft->teaser = $this->text($payload, 'teaser');
        $draft->description = $this->text($payload, 'description');

        return $draft;
    }

    #[Override]
    public function toPayload(object $draft): array
    {
        $proposal = $this->proposal($draft);

        return [
            'start' => $proposal->start?->format(DateTimeInterface::ATOM),
            'stop' => $proposal->stop?->format(DateTimeInterface::ATOM),
            'type' => $proposal->type?->value,
            'location' => $proposal->location,
            'locale' => $proposal->locale,
            'title' => $proposal->title,
            'teaser' => $proposal->teaser,
            'description' => $proposal->description,
        ];
    }

    #[Override]
    public function describe(array $payload): string
    {
        return $this->translator->trans('event_proposal.suggestion_description', [
            '%title%' => $this->text($payload, 'title'),
            '%date%' => $this->date($payload, 'start')?->format(self::DISPLAY_FORMAT) ?? '-',
        ]);
    }

    #[Override]
    public function summaryRows(array $payload): array
    {
        $type = EventType::tryFrom((int) ($payload['type'] ?? 0));

        return [
            [
                'label' => $this->translator->trans('event_proposal.form_label_start'),
                'value' => $this->date($payload, 'start')?->format(self::DISPLAY_FORMAT) ?? '',
            ],
            [
                'label' => $this->translator->trans('event_proposal.form_label_stop'),
                'value' => $this->date($payload, 'stop')?->format(self::DISPLAY_FORMAT) ?? '',
            ],
            ['label' => $this->translator->trans('event_proposal.form_label_location'), 'value' => (string) $this->location($payload)?->getName()],
            [
                'label' => $this->translator->trans('event_proposal.form_label_type'),
                'value' => $type === null ? '' : $this->translator->trans('event_proposal.type_' . strtolower($type->name)),
            ],
            ['label' => $this->translator->trans('event_proposal.summary_language'), 'value' => $this->text($payload, 'locale')],
            ['label' => $this->translator->trans('event_proposal.form_label_teaser'), 'value' => $this->text($payload, 'teaser')],
            ['label' => $this->translator->trans('event_proposal.form_label_description'), 'value' => $this->text($payload, 'description')],
        ];
    }

    #[Override]
    public function canPropose(int $userId): bool
    {
        return $this->security->isGranted('ROLE_USER') && $this->security->isGranted(PermissionAttribute::EVENT_PROPOSE);
    }

    #[Override]
    public function canReview(int $userId): bool
    {
        return $this->security->isGranted(PermissionAttribute::EVENT_CREATE);
    }

    #[Override]
    public function validate(object $draft): ?string
    {
        $proposal = $this->proposal($draft);
        if (trim($proposal->title) === '' || trim($proposal->description) === '' || $proposal->start === null) {
            return $this->translator->trans('event_proposal.validator_incomplete');
        }
        if ($proposal->start <= new DateTimeImmutable()) {
            return $this->translator->trans('event_proposal.validator_start_past');
        }
        if ($proposal->stop !== null && $proposal->stop <= $proposal->start) {
            return $this->translator->trans('event_proposal.validator_stop_before_start');
        }

        return null;
    }

    #[Override]
    public function create(object $draft, int $proposerId): int
    {
        $proposal = $this->proposal($draft);
        if ($proposal->start === null) {
            throw new InvalidArgumentException('An event proposal needs a start.');
        }
        $proposer = $this->em->getReference(User::class, $proposerId);

        $event = new Event();
        $event->setStart(DateTime::createFromImmutable($proposal->start));
        $event->setStop($proposal->stop === null ? null : DateTime::createFromImmutable($proposal->stop));
        $event->setType($proposal->type);
        $event->setLocation($this->location(['location' => $proposal->location]));
        $event->setUser($proposer);
        $event->setCreatedAt(new DateTimeImmutable());
        $event->setInitial(true);
        $event->setFeatured(false);
        $event->setStatus(EventStatus::Draft);

        $translation = new EventTranslation();
        $translation->setLanguage($proposal->locale);
        $translation->setTitle(trim($proposal->title));
        $translation->setTeaser(trim($proposal->teaser) === '' ? null : trim($proposal->teaser));
        $translation->setDescription(trim($proposal->description));
        $event->addTranslation($translation);

        $this->em->persist($event);
        $this->em->persist($translation);
        $this->em->flush();

        $createdId = (int) $event->getId();
        $reviewer = $this->security->getUser();
        $this->activityService->log(AdminEventCreated::TYPE, $reviewer instanceof User ? $reviewer : $proposer, ['event_id' => $createdId]);
        $this->entityActionDispatcher->dispatch(EntityAction::CreateEvent, $createdId);

        return $createdId;
    }

    private function proposal(object $draft): Proposal
    {
        if (!$draft instanceof Proposal) {
            throw new InvalidArgumentException(sprintf('Expected an event proposal draft, got %s', $draft::class));
        }

        return $draft;
    }

    /** @param array<string, scalar|null> $payload */
    private function location(array $payload): ?Location
    {
        $id = (int) ($payload['location'] ?? 0);

        return $id > 0 ? $this->em->find(Location::class, $id) : null;
    }

    /** @param array<string, scalar|null> $payload */
    private function date(array $payload, string $field): ?DateTimeImmutable
    {
        $value = $this->text($payload, $field);
        if ($value === '') {
            return null;
        }

        return DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $value) ?: null;
    }

    /** @param array<string, scalar|null> $payload */
    private function text(array $payload, string $field): string
    {
        return (string) ($payload[$field] ?? '');
    }

    /** @param array<string, scalar|null> $payload */
    private function optionalText(array $payload, string $field): ?string
    {
        $value = $this->text($payload, $field);

        return $value === '' ? null : $value;
    }
}
