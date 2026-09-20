<?php declare(strict_types=1);

namespace App\Suggestion;

use App\Entity\Location;
use App\Entity\User;
use App\EntityActionDispatcher;
use App\Enum\EntityAction;
use App\Filter\Admin\Location\AdminLocationListFilterService;
use App\Form\LocationType;
use App\Repository\LocationRepository;
use App\Security\Permission\Attribute\PermissionAttribute;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class LocationTarget implements SuggestionTargetProviderInterface
{
    public const string TARGET_TYPE = 'location';

    public function __construct(
        private EntityManagerInterface $em,
        private LocationRepository $repo,
        private AdminLocationListFilterService $filterService,
        private EntityActionDispatcher $entityActionDispatcher,
        private Security $security,
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
    public function getLabelKey(): string
    {
        return 'location.type_label';
    }

    #[Override]
    public function getFormType(): string
    {
        return LocationType::class;
    }

    #[Override]
    public function newDraft(): object
    {
        return new Location();
    }

    #[Override]
    public function fromPayload(array $payload): object
    {
        $draft = new Location();
        $draft->setName($this->text($payload, 'name'));
        $draft->setDescription($this->text($payload, 'description'));
        $draft->setStreet($this->text($payload, 'street'));
        $draft->setCity($this->text($payload, 'city'));
        $draft->setPostcode($this->text($payload, 'postcode'));
        $draft->setLongitude($this->optionalText($payload, 'longitude'));
        $draft->setLatitude($this->optionalText($payload, 'latitude'));

        return $draft;
    }

    #[Override]
    public function toPayload(object $draft): array
    {
        $location = $this->location($draft);

        return [
            'name' => (string) $location->getName(),
            'description' => (string) $location->getDescription(),
            'street' => (string) $location->getStreet(),
            'city' => (string) $location->getCity(),
            'postcode' => (string) $location->getPostcode(),
            'longitude' => $location->getLongitude(),
            'latitude' => $location->getLatitude(),
        ];
    }

    #[Override]
    public function describe(array $payload): string
    {
        return $this->translator->trans('location.suggestion_description', [
            '%name%' => $this->text($payload, 'name'),
            '%city%' => $this->text($payload, 'city'),
        ]);
    }

    #[Override]
    public function summaryRows(array $payload): array
    {
        $rows = [];
        foreach (['name', 'description', 'street', 'postcode', 'city'] as $field) {
            $rows[] = [
                'label' => $this->translator->trans('admin_location.form_label_' . $field),
                'value' => $this->text($payload, $field),
            ];
        }

        return $rows;
    }

    #[Override]
    public function canPropose(User $user): bool
    {
        return $this->security->isGranted('ROLE_USER');
    }

    #[Override]
    public function canReview(User $user): bool
    {
        return $this->security->isGranted(PermissionAttribute::LOCATION_CREATE);
    }

    #[Override]
    public function validate(object $draft): ?string
    {
        $location = $this->location($draft);
        $name = trim((string) $location->getName());
        $postcode = trim((string) $location->getPostcode());
        if ($name === '' || $postcode === '') {
            return $this->translator->trans('location.validator_incomplete');
        }

        return $this->isDuplicate($location, $name, $postcode) ? $this->translator->trans('location.validator_duplicate') : null;
    }

    #[Override]
    public function create(object $draft, User $proposer): int
    {
        $location = $this->location($draft);
        $location->setUser($proposer);
        $location->setCreatedAt(new DateTimeImmutable());

        $this->em->persist($location);
        $this->em->flush();

        $createdId = (int) $location->getId();
        $this->entityActionDispatcher->dispatch(EntityAction::CreateLocation, $createdId);

        return $createdId;
    }

    private function location(object $draft): Location
    {
        if (!$draft instanceof Location) {
            throw new SuggestionException(sprintf('Expected a Location draft, got %s', $draft::class));
        }

        return $draft;
    }

    private function isDuplicate(Location $draft, string $name, string $postcode): bool
    {
        $visible = $this->repo->findAllForAdmin($this->filterService->getLocationIdFilter()->getLocationIds());

        return array_any(
            $visible,
            static fn(Location $existing): bool => (
                $existing->getId() !== $draft->getId()
                && strcasecmp(trim((string) $existing->getName()), $name) === 0
                && strcasecmp(trim((string) $existing->getPostcode()), $postcode) === 0
            ),
        );
    }

    /** @param array<string, scalar|null> $payload */
    private function text(array $payload, string $field): string
    {
        return (string) ($payload[$field] ?? '');
    }

    /** @param array<string, scalar|null> $payload */
    private function optionalText(array $payload, string $field): ?string
    {
        $value = (string) ($payload[$field] ?? '');

        return $value === '' ? null : $value;
    }
}
