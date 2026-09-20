<?php declare(strict_types=1);

namespace App\Review;

use App\Contribution\LocationSection;
use App\Contribution\Registry;
use App\Entity\Location;
use App\Entity\User;
use App\EntityActionDispatcher;
use App\Enum\EntityAction;
use App\Filter\Admin\Location\AdminLocationListFilterService;
use App\Repository\LocationRepository;
use App\Security\Permission\Attribute\PermissionAttribute;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class LocationChangeTarget implements ChangeTargetProviderInterface
{
    public const string TARGET_TYPE = 'location';

    private const array FIELDS = ['name', 'description', 'street', 'city', 'postcode', 'longitude', 'latitude'];
    private const array REQUIRED_FIELDS = ['name', 'description', 'street', 'city', 'postcode'];

    public function __construct(
        private EntityManagerInterface $em,
        private LocationRepository $repo,
        private AdminLocationListFilterService $filterService,
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
        return $this->visibleLocation($targetId)?->getName();
    }

    #[Override]
    public function getTargetUrl(int $targetId): ?string
    {
        if ($this->visibleLocation($targetId) === null) {
            return null;
        }

        return $this->router->generate('app_admin_location_edit', ['id' => $targetId]);
    }

    #[Override]
    public function getFieldLabel(string $field): string
    {
        return in_array($field, self::FIELDS, true) ? $this->translator->trans('admin_location.form_label_' . $field) : $field;
    }

    #[Override]
    public function formatValue(string $field, ?string $value): string
    {
        return $value ?? '';
    }

    #[Override]
    public function canPropose(User $user, int $targetId): bool
    {
        return $this->security->isGranted('ROLE_USER') && $this->contributions->mayTouch(LocationSection::TYPE, $user, $targetId);
    }

    #[Override]
    public function canReview(User $user, int $targetId): bool
    {
        return $this->security->isGranted(PermissionAttribute::LOCATION_CREATE) && $this->visibleLocation($targetId) !== null;
    }

    #[Override]
    public function validate(int $targetId, string $field, ?string $value): ?string
    {
        if ($this->visibleLocation($targetId) === null) {
            return $this->translator->trans('location.validator_gone');
        }

        if (!in_array($field, self::FIELDS, true)) {
            return $this->translator->trans('location.validator_unknown_field');
        }

        $isRequired = in_array($field, self::REQUIRED_FIELDS, true);

        return $isRequired && trim((string) $value) === '' ? $this->translator->trans('location.validator_blank') : null;
    }

    #[Override]
    public function apply(int $targetId, string $field, ?string $value): void
    {
        $location = $this->visibleLocation($targetId);
        if ($location === null) {
            return;
        }

        match ($field) {
            'name' => $location->setName((string) $value),
            'description' => $location->setDescription((string) $value),
            'street' => $location->setStreet((string) $value),
            'city' => $location->setCity((string) $value),
            'postcode' => $location->setPostcode((string) $value),
            'longitude' => $location->setLongitude($value === '' ? null : $value),
            'latitude' => $location->setLatitude($value === '' ? null : $value),
            default => null,
        };

        $this->em->flush();
        $this->entityActionDispatcher->dispatch(EntityAction::UpdateLocation, $targetId);
    }

    private function visibleLocation(int $targetId): ?Location
    {
        if (!$this->filterService->isLocationAccessible($targetId)) {
            return null;
        }

        return $this->repo->find($targetId);
    }
}
