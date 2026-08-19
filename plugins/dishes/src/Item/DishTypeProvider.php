<?php declare(strict_types=1);

namespace Plugin\Dishes\Item;

use App\Entity\EventItemAssociation;
use App\Entity\User;
use App\Enum\ItemViewType;
use App\Item\TypeProviderInterface;
use App\Item\ListCellProviderInterface;
use App\Item\ListProviderInterface;
use Override;
use Plugin\Dishes\Entity\Dish;
use Plugin\Dishes\Repository\DishLikeRepository;
use Plugin\Dishes\Service\ConfigService;
use Plugin\Dishes\Service\DishService;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Environment;

final readonly class DishTypeProvider implements TypeProviderInterface, ListCellProviderInterface, ListProviderInterface
{
    public function __construct(
        private DishService $dishService,
        private Environment $twig,
        private ConfigService $configService,
        private DishLikeRepository $dishLikeRepository,
        private Security $security,
    ) {}

    #[Override]
    public function getPluginKey(): string
    {
        return 'dishes';
    }

    #[Override]
    public function getKey(): string
    {
        return DishService::ITEM_TYPE;
    }

    #[Override]
    public function getLabelKey(): string
    {
        return 'dishes.item_label';
    }

    #[Override]
    public function renderEventCell(int $itemId, EventItemAssociation $association): ?string
    {
        $dish = $this->dishService->getAttached($itemId);
        if ($dish === null) {
            return null;
        }

        return $this->twig->render('@Dishes/item/event_cell.html.twig', [
            'dish' => $dish,
            'association' => $association,
            'sectionLabel' => $association->getSectionLabel(),
        ]);
    }

    #[Override]
    public function renderListCell(int $itemId, ?ItemViewType $mode = null): ?string
    {
        $dish = $this->dishService->get($itemId);
        if ($dish === null) {
            return null;
        }

        return $this->twig->render('@Dishes/item/list_cell.html.twig', [
            'dish' => $dish,
            'viewMode' => $mode?->value,
        ]);
    }

    #[Override]
    public function getItemIds(): array
    {
        return array_values(array_map(static fn(Dish $dish): int => (int) $dish->getId(), $this->dishService->getList()));
    }

    #[Override]
    public function renderList(): string
    {
        $user = $this->security->getUser();

        return $this->twig->render('@Dishes/item/list_body.html.twig', [
            'dishes' => $this->dishService->getList(),
            'showPhonetic' => $this->configService->getConfig()->isPhoneticInList(),
            'favoriteDishIds' => $user instanceof User ? $this->dishLikeRepository->findDishIdsByUser($user->getId()) : [],
        ]);
    }

    #[Override]
    public function getListRoute(): string
    {
        return 'app_dishes_dishlist';
    }

    #[Override]
    public function getDetailRoute(): ?string
    {
        return 'app_plugin_dishes_dish_show';
    }

    #[Override]
    public function isDetailIndexable(): bool
    {
        return true;
    }

    #[Override]
    public function getLastmodByItemId(array $itemIds): array
    {
        $wanted = array_flip($itemIds);

        $stamps = [];
        foreach ($this->dishService->getList() as $dish) {
            $id = (int) $dish->getId();
            $createdAt = $dish->getCreatedAt();
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
        return $this->twig->render('@Dishes/item/attach_picker.html.twig', [
            'eventId' => $eventId,
            'dishes' => $this->dishService->getManagedList(),
        ]);
    }

    #[Override]
    public function getPriority(): int
    {
        return 30;
    }
}
