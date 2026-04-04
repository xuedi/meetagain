<?php declare(strict_types=1);

namespace Plugin\Dinnerclub\Notification;

use App\Service\Notification\Admin\AdminNotificationItem;
use App\Service\Notification\Admin\AdminNotificationProviderInterface;
use Plugin\Dinnerclub\Entity\Dish;
use Plugin\Dinnerclub\Repository\DishRepository;

readonly class DinnerclubSuggestionAdminNotificationProvider implements AdminNotificationProviderInterface
{
    public function __construct(
        private DishRepository $dishRepository,
    ) {}

    public function getSection(): string
    {
        return 'Dinnerclub: Pending Dish Suggestions';
    }

    public function getPendingItems(): array
    {
        $items = [];

        foreach ($this->dishRepository->findWithSuggestions() as $dish) {
            $items[] = new AdminNotificationItem(
                label: $this->getDishName($dish),
                route: 'plugin_dinnerclub_suggestion_view',
                routeParams: ['id' => $dish->getId()],
            );
        }

        return $items;
    }

    public function getLatestPendingAt(): ?\DateTimeImmutable
    {
        return $this->dishRepository->getLatestSuggestionCreatedAt();
    }

    private function getDishName(Dish $dish): string
    {
        $originLang = $dish->getOriginLang();
        if ($originLang !== null) {
            $translation = $dish->findTranslation($originLang);
            if ($translation !== null) {
                return $translation->getName();
            }
        }

        $first = $dish->getTranslations()->first();

        return $first !== false ? $first->getName() : '[unknown]';
    }
}
