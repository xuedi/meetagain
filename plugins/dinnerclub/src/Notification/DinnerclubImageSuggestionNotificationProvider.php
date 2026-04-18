<?php declare(strict_types=1);

namespace Plugin\Dinnerclub\Notification;

use App\Activity\ActivityService;
use App\Entity\User;
use App\Service\Notification\User\ReviewNotificationItem;
use App\Service\Notification\User\ReviewNotificationProviderInterface;
use Plugin\Dinnerclub\Activity\Messages\ImageSuggestionApproved;
use Plugin\Dinnerclub\Activity\Messages\ImageSuggestionRejected;
use Plugin\Dinnerclub\Repository\DishImageSuggestionRepository;
use Plugin\Dinnerclub\Service\DishService;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

readonly class DinnerclubImageSuggestionNotificationProvider implements ReviewNotificationProviderInterface
{
    public function __construct(
        private DishImageSuggestionRepository $repository,
        private DishService $dishService,
        private ActivityService $activityService,
        private Security $security,
    ) {}


    public function getIdentifier(): string
    {
        return 'dinnerclub.image_suggestion';
    }

    public function getReviewItems(User $user): array
    {
        if (!$this->security->isGranted('ROLE_ORGANIZER')) {
            return [];
        }

        $suggestions = $this->repository->findAll();
        $items = [];

        foreach ($suggestions as $suggestion) {
            $dishName = $suggestion->getDish()?->getAnyTranslatedName() ?: '[unknown dish]';
            $suggestedBy = $suggestion->getSuggestedBy() ?? 0;

            $items[] = new ReviewNotificationItem(
                id: (string) $suggestion->getId(),
                description: sprintf("Image suggestion for '%s' by user #%d", $dishName, $suggestedBy),
                canDeny: true,
                icon: 'image',
            );
        }

        return $items;
    }

    public function approveItem(User $user, string $itemId): void
    {
        if (!$this->security->isGranted('ROLE_ORGANIZER')) {
            throw new AccessDeniedException('Only organizers can approve image suggestions.');
        }

        $suggestion = $this->repository->find((int) $itemId);
        $this->dishService->applyImageSuggestion((int) $itemId);

        if ($suggestion !== null) {
            $this->activityService->log(ImageSuggestionApproved::TYPE, $user, [
                'dish_id' => $suggestion->getDish()?->getId(),
                'dish_name' => $suggestion->getDish()?->getAnyTranslatedName() ?: '[unknown]',
                'suggestion_type' => $suggestion->getType()?->value,
            ]);
        }
    }

    public function denyItem(User $user, string $itemId): void
    {
        if (!$this->security->isGranted('ROLE_ORGANIZER')) {
            throw new AccessDeniedException('Only organizers can deny image suggestions.');
        }

        $suggestion = $this->repository->find((int) $itemId);
        $this->dishService->denyImageSuggestion((int) $itemId);

        if ($suggestion !== null) {
            $this->activityService->log(ImageSuggestionRejected::TYPE, $user, [
                'dish_id' => $suggestion->getDish()?->getId(),
                'dish_name' => $suggestion->getDish()?->getAnyTranslatedName() ?: '[unknown]',
                'suggestion_type' => $suggestion->getType()?->value,
            ]);
        }
    }
}
