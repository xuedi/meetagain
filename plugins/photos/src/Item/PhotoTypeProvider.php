<?php declare(strict_types=1);

namespace Plugin\Photos\Item;

use App\Entity\EventItemAssociation;
use App\Enum\ItemViewType;
use App\Item\ListCellProviderInterface;
use App\Item\ListProviderInterface;
use App\Item\TypeProviderInterface;
use Override;
use Plugin\Photos\Entity\Photo;
use Plugin\Photos\Service\ConfigService;
use Plugin\Photos\Service\PhotoService;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Twig\Environment;

final readonly class PhotoTypeProvider implements TypeProviderInterface, ListCellProviderInterface, ListProviderInterface
{
    public function __construct(
        private PhotoService $photoService,
        private ConfigService $configService,
        private AuthorizationCheckerInterface $authorizationChecker,
        private Environment $twig,
    ) {}

    #[Override]
    public function getPluginKey(): string
    {
        return 'photos';
    }

    #[Override]
    public function getKey(): string
    {
        return PhotoService::ITEM_TYPE;
    }

    #[Override]
    public function getLabelKey(): string
    {
        return 'photos.item_label';
    }

    #[Override]
    public function renderEventCell(int $itemId, EventItemAssociation $association): ?string
    {
        $photo = $this->photoService->getAttached($itemId);
        if ($photo === null) {
            return null;
        }

        return $this->twig->render('@Photos/item/event_cell.html.twig', [
            'photo' => $photo,
            'association' => $association,
        ]);
    }

    #[Override]
    public function renderListCell(int $itemId, ?ItemViewType $mode = null): ?string
    {
        $photo = $this->photoService->get($itemId);
        if ($photo === null) {
            return null;
        }

        return $this->twig->render('@Photos/item/list_cell.html.twig', [
            'photo' => $photo,
            'viewMode' => $mode?->value,
        ]);
    }

    #[Override]
    public function getItemIds(): array
    {
        return array_values(array_map(static fn(Photo $photo): int => (int) $photo->getId(), $this->photoService->getList()));
    }

    #[Override]
    public function renderList(): string
    {
        return $this->twig->render('@Photos/item/list_body.html.twig', [
            'photos' => $this->photoService->getList(),
            'canAdd' => $this->canAdd(),
        ]);
    }

    public function canAdd(): bool
    {
        if ($this->authorizationChecker->isGranted('ROLE_STEWARD')) {
            return true;
        }

        return $this->authorizationChecker->isGranted('ROLE_USER') && $this->configService->getConfig()->isMemberUploads();
    }

    #[Override]
    public function getListRoute(): string
    {
        return 'app_photos_photolist';
    }

    #[Override]
    public function getDetailRoute(): ?string
    {
        return 'app_plugin_photos_photo_show';
    }

    #[Override]
    public function getLastmodByItemId(array $itemIds): array
    {
        $wanted = array_flip($itemIds);

        $stamps = [];
        foreach ($this->photoService->getList() as $photo) {
            $id = (int) $photo->getId();
            $createdAt = $photo->getCreatedAt();
            if ($createdAt === null || !isset($wanted[$id])) {
                continue;
            }

            $stamps[$id] = $createdAt;
        }

        return $stamps;
    }

    #[Override]
    public function renderAttachPicker(int $eventId): string
    {
        return $this->twig->render('@Photos/item/attach_picker.html.twig', [
            'eventId' => $eventId,
            'photos' => $this->photoService->getManagedList(),
        ]);
    }

    #[Override]
    public function getPriority(): int
    {
        return 40;
    }
}
