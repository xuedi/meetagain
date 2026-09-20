<?php declare(strict_types=1);

namespace App\Contribution;

use App\Entity\Location;
use App\Entity\User;
use App\Filter\Event\EventFilterService;
use App\Form\LocationType;
use App\Repository\EventRepository;
use App\Repository\LocationRepository;
use App\Review\FieldChange;
use App\Review\LocationChangeTarget;
use Override;
use Symfony\Component\Form\FormInterface;

readonly class LocationSection implements RowFormInterface
{
    public const string TYPE = 'location';

    private const array PROPOSABLE_FIELDS = ['name', 'description', 'street', 'city', 'postcode', 'longitude', 'latitude'];

    public function __construct(
        private LocationRepository $locationRepo,
        private EventRepository $eventRepo,
        private EventFilterService $eventFilter,
        private ScopeFilterService $scope,
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
        return 'contribution.section_venues';
    }

    #[Override]
    public function getIcon(): string
    {
        return 'fa-location-dot';
    }

    #[Override]
    public function listForMember(User $user): array
    {
        $entries = [];
        foreach ($this->locationRepo->findAllForAdmin() as $venue) {
            $entries[] = new Entry((int) $venue->getId(), (string) $venue->getName(), $this->addressOf($venue));
        }

        return $entries;
    }

    #[Override]
    public function mayTouch(User $user, int|string $id): bool
    {
        $id = (int) $id;
        if ($this->locationRepo->find($id) === null) {
            return false;
        }

        if ($this->eventFilter->getAccessibleEventIds($this->eventRepo->findIdsByLocation($id)) !== []) {
            return true;
        }

        return $this->scope->allows(self::TYPE, $id, $user);
    }

    #[Override]
    public function getTargetType(): string
    {
        return LocationChangeTarget::TARGET_TYPE;
    }

    #[Override]
    public function getFormType(): string
    {
        return LocationType::class;
    }

    #[Override]
    public function draftFor(int|string $id): ?Draft
    {
        $venue = $this->locationRepo->find((int) $id);

        return $venue === null ? null : new Draft($this->detachedCopy($venue), (string) $venue->getName(), 'contribution.correct_intro');
    }

    #[Override]
    public function changesFrom(int|string $id, FormInterface $form): array
    {
        $venue = $this->locationRepo->find((int) $id);
        $submitted = $form->getData();
        if ($venue === null || !$submitted instanceof Location) {
            return [];
        }

        $before = $this->fieldValues($venue);
        $after = $this->fieldValues($submitted);

        $changes = [];
        foreach (self::PROPOSABLE_FIELDS as $field) {
            $changes[] = new FieldChange($field, $before[$field] ?? null, $after[$field] ?? null);
        }

        return $changes;
    }

    private function detachedCopy(Location $venue): Location
    {
        $copy = new Location();
        $copy->setName((string) $venue->getName());
        $copy->setDescription((string) $venue->getDescription());
        $copy->setStreet((string) $venue->getStreet());
        $copy->setCity((string) $venue->getCity());
        $copy->setPostcode((string) $venue->getPostcode());
        $copy->setLongitude($venue->getLongitude());
        $copy->setLatitude($venue->getLatitude());

        return $copy;
    }

    /**
     * @return array<string, ?string>
     */
    private function fieldValues(Location $venue): array
    {
        return [
            'name' => $venue->getName(),
            'description' => $venue->getDescription(),
            'street' => $venue->getStreet(),
            'city' => $venue->getCity(),
            'postcode' => $venue->getPostcode(),
            'longitude' => $venue->getLongitude(),
            'latitude' => $venue->getLatitude(),
        ];
    }

    private function addressOf(Location $venue): ?string
    {
        $parts = array_filter([$venue->getStreet(), $venue->getCity()]);

        return $parts === [] ? null : implode(', ', $parts);
    }
}
