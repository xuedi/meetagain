<?php declare(strict_types=1);

namespace App\Contribution;

use App\Entity\Event;
use App\Entity\User;
use App\Filter\Event\EventFilterService;
use App\Form\EventCorrectionType;
use App\Repository\EventRepository;
use App\Review\EventChangeTarget;
use App\Review\FieldChange;
use Override;
use Symfony\Component\Form\FormInterface;

readonly class EventSection implements RowFormInterface
{
    public const string TYPE = 'event';

    public function __construct(
        private EventRepository $eventRepo,
        private EventFilterService $eventFilter,
        private EventChangeTarget $changeTarget,
    ) {}

    #[Override]
    public function getType(): string
    {
        return self::TYPE;
    }

    #[Override]
    public function getPluginKey(): string
    {
        return '';
    }

    #[Override]
    public function getLabelKey(): string
    {
        return 'contribution.section_events';
    }

    #[Override]
    public function getIcon(): string
    {
        return 'fa-calendar-day';
    }

    #[Override]
    public function listForMember(User $user): array
    {
        $entries = [];
        foreach ($this->eventRepo->findAllForAdmin($this->eventFilter->getEventIdFilter()->getEventIds()) as $event) {
            $entries[] = new Entry((int) $event->getId(), $this->titleOf($event), $event->getStart()?->format('Y-m-d'));
        }

        return $entries;
    }

    #[Override]
    public function mayTouch(User $user, int|string $id): bool
    {
        return $this->eventFilter->isEventAccessible((int) $id) && $this->eventRepo->find((int) $id) instanceof Event;
    }

    #[Override]
    public function getTargetType(): string
    {
        return EventChangeTarget::TARGET_TYPE;
    }

    #[Override]
    public function getFormType(): string
    {
        return EventCorrectionType::class;
    }

    #[Override]
    public function draftFor(int|string $id): ?Draft
    {
        $event = $this->eventRepo->find((int) $id);
        if (!$event instanceof Event) {
            return null;
        }

        $labels = [];
        foreach ($this->changeTarget->fieldsFor($event) as $field) {
            $labels[$field] = $this->changeTarget->getFieldLabel($field);
        }

        return new Draft($this->currentValues($event), $this->titleOf($event), 'contribution.event_correct_intro', ['fields' => $labels]);
    }

    #[Override]
    public function changesFrom(int|string $id, FormInterface $form): array
    {
        $event = $this->eventRepo->find((int) $id);
        $submitted = $form->getData();
        if (!$event instanceof Event || !is_array($submitted)) {
            return [];
        }

        $before = $this->currentValues($event);

        $changes = [];
        foreach ($submitted as $field => $value) {
            $changes[] = new FieldChange((string) $field, $before[(string) $field] ?? null, $value === null ? null : (string) $value);
        }

        return $changes;
    }

    /**
     * @return array<string, ?string>
     */
    private function currentValues(Event $event): array
    {
        $values = [];
        foreach ($this->changeTarget->fieldsFor($event) as $field) {
            $values[$field] = $this->changeTarget->currentValue($event, $field);
        }

        return $values;
    }

    private function titleOf(Event $event): string
    {
        foreach ($event->getTranslation() as $translation) {
            $title = $translation->getTitle();
            if ($title !== null && $title !== '') {
                return $title;
            }
        }

        return '#' . $event->getId();
    }
}
