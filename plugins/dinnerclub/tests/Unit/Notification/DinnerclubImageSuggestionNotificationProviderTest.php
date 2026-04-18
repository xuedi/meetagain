<?php declare(strict_types=1);

namespace Tests\Plugin\Dinnerclub\Unit\Notification;

use App\Entity\User;
use App\Service\Notification\User\ReviewNotificationItem;
use PHPUnit\Framework\TestCase;
use Plugin\Dinnerclub\Entity\DishImageSuggestion;
use Plugin\Dinnerclub\Notification\DinnerclubImageSuggestionNotificationProvider;
use Plugin\Dinnerclub\Repository\DishImageSuggestionRepository;
use Plugin\Dinnerclub\Service\DishService;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class DinnerclubImageSuggestionNotificationProviderTest extends TestCase
{
    private function makeSuggestion(int $id = 1): DishImageSuggestion
    {
        $suggestion = $this->createMock(DishImageSuggestion::class);
        $suggestion->method('getId')->willReturn($id);
        $suggestion->method('getDish')->willReturn(null);
        $suggestion->method('getSuggestedBy')->willReturn(42);

        return $suggestion;
    }

    private function makeProvider(array $suggestions = [], bool $isOrganizer = true): DinnerclubImageSuggestionNotificationProvider
    {
        $repo = $this->createMock(DishImageSuggestionRepository::class);
        $repo->method('findAll')->willReturn($suggestions);

        $security = $this->createStub(Security::class);
        $security->method('isGranted')->willReturn($isOrganizer);

        return new DinnerclubImageSuggestionNotificationProvider(
            repository: $repo,
            dishService: $this->createStub(DishService::class),
            security: $security,
        );
    }

    public function testGetReviewItemsReturnsOneItemPerSuggestion(): void
    {
        // Arrange
        $user = $this->createStub(User::class);
        $provider = $this->makeProvider(suggestions: [$this->makeSuggestion(1), $this->makeSuggestion(2)]);

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
        $provider = $this->makeProvider(suggestions: [$this->makeSuggestion()], isOrganizer: false);

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
