<?php declare(strict_types=1);

namespace Tests\Plugin\Dinnerclub\Unit\Notification;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\Notification\User\ReviewNotificationItem;
use PHPUnit\Framework\TestCase;
use Plugin\Dinnerclub\Entity\Dish;
use Plugin\Dinnerclub\Notification\DinnerclubSuggestionNotificationProvider;
use Plugin\Dinnerclub\Service\DishService;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class DinnerclubSuggestionNotificationProviderTest extends TestCase
{
    private function makeDish(int $id = 1, string $name = 'Peking Duck'): Dish
    {
        $dish = $this->createMock(Dish::class);
        $dish->method('getId')->willReturn($id);
        $dish->method('getAnyTranslatedName')->willReturn($name);
        $dish->method('getCreatedBy')->willReturn(null);

        return $dish;
    }

    private function makeProvider(array $pendingDishes = [], bool $isOrganizer = true): DinnerclubSuggestionNotificationProvider
    {
        $dishService = $this->createMock(DishService::class);
        $dishService->method('getPendingDishes')->willReturn($pendingDishes);

        $security = $this->createStub(Security::class);
        $security->method('isGranted')->willReturn($isOrganizer);

        return new DinnerclubSuggestionNotificationProvider(
            dishService: $dishService,
            userRepository: $this->createStub(UserRepository::class),
            security: $security,
        );
    }

    public function testGetReviewItemsReturnsOneItemPerPendingDish(): void
    {
        // Arrange
        $user = $this->createStub(User::class);
        $provider = $this->makeProvider(pendingDishes: [$this->makeDish(1), $this->makeDish(2)]);

        // Act
        $items = $provider->getReviewItems($user);

        // Assert
        static::assertCount(2, $items);
        static::assertInstanceOf(ReviewNotificationItem::class, $items[0]);
        static::assertSame('1', $items[0]->id);
    }

    public function testGetReviewItemsReturnsEmptyForNonOrganizer(): void
    {
        // Arrange
        $user = $this->createStub(User::class);
        $provider = $this->makeProvider(pendingDishes: [$this->makeDish()], isOrganizer: false);

        // Act
        $items = $provider->getReviewItems($user);

        // Assert
        static::assertSame([], $items);
    }

    public function testApproveItemThrowsForNonOrganizer(): void
    {
        // Arrange
        $user = $this->createStub(User::class);
        $provider = $this->makeProvider(isOrganizer: false);

        // Assert
        $this->expectException(AccessDeniedException::class);

        // Act
        $provider->approveItem($user, '1');
    }

    public function testDenyItemThrowsForNonOrganizer(): void
    {
        // Arrange
        $user = $this->createStub(User::class);
        $provider = $this->makeProvider(isOrganizer: false);

        // Assert
        $this->expectException(AccessDeniedException::class);

        // Act
        $provider->denyItem($user, '1');
    }
}
