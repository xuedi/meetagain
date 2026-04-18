<?php declare(strict_types=1);

namespace Plugin\Dinnerclub\Notification;

use App\Activity\ActivityService;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\Notification\User\ReviewNotificationItem;
use App\Service\Notification\User\ReviewNotificationProviderInterface;
use Plugin\Dinnerclub\Activity\Messages\DishApproved;
use Plugin\Dinnerclub\Activity\Messages\DishRejected;
use Plugin\Dinnerclub\Service\DishService;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

readonly class DinnerclubSuggestionNotificationProvider implements ReviewNotificationProviderInterface
{
    public function __construct(
        private DishService $dishService,
        private UserRepository $userRepository,
        private ActivityService $activityService,
        private Security $security,
    ) {}


    public function getIdentifier(): string
    {
        return 'dinnerclub.dish_suggestion';
    }

    public function getReviewItems(User $user): array
    {
        if (!$this->security->isGranted('ROLE_ORGANIZER')) {
            return [];
        }

        $pendingDishes = $this->dishService->getPendingDishes();
        $items = [];

        foreach ($pendingDishes as $dish) {
            $creatorName = 'unknown';
            if ($dish->getCreatedBy() !== null) {
                $creator = $this->userRepository->find($dish->getCreatedBy());
                if ($creator !== null) {
                    $creatorName = $creator->getName();
                }
            }

            $name = $dish->getAnyTranslatedName() ?: '[unnamed]';
            $items[] = new ReviewNotificationItem(
                id: (string) $dish->getId(),
                description: sprintf("Dish suggestion '%s' by %s", $name, $creatorName),
                canDeny: true,
                icon: 'utensils',
            );
        }

        return $items;
    }

    public function approveItem(User $user, string $itemId): void
    {
        if (!$this->security->isGranted('ROLE_ORGANIZER')) {
            throw new AccessDeniedException('Only organizers can approve dish suggestions.');
        }

        $dish = $this->dishService->getDish((int) $itemId);
        $this->dishService->approveDish((int) $itemId);

        if ($dish !== null) {
            $this->activityService->log(DishApproved::TYPE, $user, [
                'dish_id' => $dish->getId(),
                'dish_name' => $dish->getAnyTranslatedName() ?: '[unnamed]',
            ]);
        }
    }

    public function denyItem(User $user, string $itemId): void
    {
        if (!$this->security->isGranted('ROLE_ORGANIZER')) {
            throw new AccessDeniedException('Only organizers can reject dish suggestions.');
        }

        $dish = $this->dishService->getDish((int) $itemId);
        $dishId = $dish?->getId();
        $dishName = $dish?->getAnyTranslatedName() ?: '[unnamed]';

        $this->dishService->rejectDish((int) $itemId);

        if ($dish !== null) {
            $this->activityService->log(DishRejected::TYPE, $user, [
                'dish_id' => $dishId,
                'dish_name' => $dishName,
            ]);
        }
    }
}
