<?php declare(strict_types=1);

namespace App\Review;

use App\Contribution\EventSection;
use App\Contribution\Registry;
use App\Entity\Event;
use App\Entity\EventTranslation;
use App\Entity\User;
use App\EntityActionDispatcher;
use App\Enum\EntityAction;
use App\Filter\Event\EventFilterService;
use App\Repository\EventRepository;
use App\Security\Permission\Attribute\PermissionAttribute;
use App\Service\Event\RecurringService;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class EventChangeTarget implements ChangeTargetProviderInterface, ScopedChangeTargetInterface
{
    public const string TARGET_TYPE = 'event';
    public const string SCOPE_SERIES = 'series';

    private const array PROPERTIES = ['title', 'teaser', 'description'];
    private const array REQUIRED_PROPERTIES = ['title'];

    public function __construct(
        private EntityManagerInterface $em,
        private EventRepository $repo,
        private EventFilterService $eventFilter,
        private RecurringService $recurringService,
        private Registry $contributions,
        private EntityActionDispatcher $entityActionDispatcher,
        private Security $security,
        private RouterInterface $router,
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
        return self::TARGET_TYPE;
    }

    #[Override]
    public function getTargetLabel(int $targetId): ?string
    {
        $event = $this->visibleEvent($targetId);

        return $event === null ? null : $this->anyTitle($event);
    }

    #[Override]
    public function getTargetUrl(int $targetId): ?string
    {
        if ($this->visibleEvent($targetId) === null) {
            return null;
        }

        return $this->router->generate('app_event_details', ['id' => $targetId]);
    }

    #[Override]
    public function getFieldLabel(string $field): string
    {
        $property = $this->propertyOf($field);
        if (!in_array($property, self::PROPERTIES, true)) {
            return $field;
        }

        return $this->translator->trans('contribution.event_field_' . $property, [
            '%locale%' => strtoupper($this->localeOf($field)),
        ]);
    }

    #[Override]
    public function formatValue(string $field, ?string $value): string
    {
        return $value ?? '';
    }

    #[Override]
    public function canPropose(User $user, int $targetId): bool
    {
        return $this->security->isGranted('ROLE_USER') && $this->contributions->mayTouch(EventSection::TYPE, $user, $targetId);
    }

    #[Override]
    public function canReview(User $user, int $targetId): bool
    {
        $event = $this->repo->find($targetId);

        return $event instanceof Event && $this->security->isGranted(PermissionAttribute::EVENT_UPDATE, $event);
    }

    #[Override]
    public function validate(int $targetId, string $field, ?string $value): ?string
    {
        $event = $this->visibleEvent($targetId);
        if ($event === null) {
            return $this->translator->trans('contribution.event_validator_gone');
        }

        $property = $this->propertyOf($field);
        if (!in_array($property, self::PROPERTIES, true) || $event->findTranslation($this->localeOf($field)) === null) {
            return $this->translator->trans('contribution.event_validator_unknown_field');
        }

        if (in_array($property, self::REQUIRED_PROPERTIES, true) && trim((string) $value) === '') {
            return $this->translator->trans('contribution.event_validator_blank');
        }

        return null;
    }

    #[Override]
    public function apply(int $targetId, string $field, ?string $value): void
    {
        $translation = $this->visibleEvent($targetId)?->findTranslation($this->localeOf($field));
        if (!$translation instanceof EventTranslation) {
            return;
        }

        $this->write($translation, $field, $value);

        $this->em->flush();
        $this->entityActionDispatcher->dispatch(EntityAction::UpdateEvent, $targetId);
    }

    #[Override]
    public function getApplyScope(int $targetId): ?array
    {
        $event = $this->visibleEvent($targetId);
        if ($event === null) {
            return null;
        }

        $followers = $this->recurringService->seriesFollowers($event);
        $count = count($followers['writable']);
        if ($count === 0) {
            return null;
        }

        return [
            'key' => self::SCOPE_SERIES,
            'label' => $followers['locked'] === 0
                ? $this->translator->trans('review.button_apply_series', ['%count%' => $count])
                : $this->translator->trans('review.button_apply_series_locked', ['%count%' => $count, '%locked%' => $followers['locked']]),
        ];
    }

    #[Override]
    public function applyToScope(int $targetId, string $scope, string $field, ?string $value): void
    {
        $event = $this->visibleEvent($targetId);
        if ($scope !== self::SCOPE_SERIES || $event === null) {
            return;
        }

        $this->recurringService->writeFollowerTranslations($event, $this->localeOf($field), fn(EventTranslation $translation) => $this->write(
            $translation,
            $field,
            $value,
        ));
    }

    /**
     * @return list<string>
     */
    public function fieldsFor(Event $event): array
    {
        $fields = [];
        foreach ($event->getTranslation() as $translation) {
            foreach (self::PROPERTIES as $property) {
                $fields[] = $property . '_' . $translation->getLanguage();
            }
        }

        return $fields;
    }

    public function currentValue(Event $event, string $field): ?string
    {
        $translation = $event->findTranslation($this->localeOf($field));
        if (!$translation instanceof EventTranslation) {
            return null;
        }

        return match ($this->propertyOf($field)) {
            'title' => $translation->getTitle(),
            'teaser' => $translation->getTeaser(),
            'description' => $translation->getDescription(),
            default => null,
        };
    }

    private function write(EventTranslation $translation, string $field, ?string $value): void
    {
        match ($this->propertyOf($field)) {
            'title' => $translation->setTitle((string) $value),
            'teaser' => $translation->setTeaser($value === '' ? null : $value),
            'description' => $translation->setDescription((string) $value),
            default => null,
        };
    }

    private function visibleEvent(int $targetId): ?Event
    {
        if (!$this->eventFilter->isEventAccessible($targetId)) {
            return null;
        }

        return $this->repo->find($targetId);
    }

    private function anyTitle(Event $event): ?string
    {
        foreach ($event->getTranslation() as $translation) {
            $title = $translation->getTitle();
            if ($title !== null && $title !== '') {
                return $title;
            }
        }

        return null;
    }

    private function propertyOf(string $field): string
    {
        return explode('_', $field)[0];
    }

    private function localeOf(string $field): string
    {
        return explode('_', $field)[1] ?? '';
    }
}
