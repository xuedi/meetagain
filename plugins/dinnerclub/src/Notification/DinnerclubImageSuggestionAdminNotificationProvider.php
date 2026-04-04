<?php declare(strict_types=1);

namespace Plugin\Dinnerclub\Notification;

use App\Service\Notification\Admin\AdminNotificationItem;
use App\Service\Notification\Admin\AdminNotificationProviderInterface;
use Plugin\Dinnerclub\Entity\Dish;
use Plugin\Dinnerclub\Repository\DishImageSuggestionRepository;

readonly class DinnerclubImageSuggestionAdminNotificationProvider implements AdminNotificationProviderInterface
{
    public function __construct(
        private DishImageSuggestionRepository $repository,
    ) {}

    public function getSection(): string
    {
        return 'Dinnerclub: Pending Image Suggestions';
    }

    public function getPendingItems(): array
    {
        $items = [];

        foreach ($this->repository->findDishesWithPendingSuggestions() as $dish) {
            $items[] = new AdminNotificationItem(
                label: $this->getDishName($dish),
                route: 'plugin_dinnerclub_image_suggestion_view',
                routeParams: ['id' => $dish->getId()],
            );
        }

        return $items;
    }

    public function getLatestPendingAt(): ?\DateTimeImmutable
    {
        return $this->repository->getLatestCreatedAt();
    }

    private function getDishName(Dish $dish): string
    {
        return $dish->getAnyTranslatedName() ?: '[unknown]';
    }
}
