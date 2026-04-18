<?php declare(strict_types=1);

namespace Tests\Unit\Service\Notification\User;

use App\Entity\User;
use App\Service\Notification\User\ReviewNotificationItem;
use App\Service\Notification\User\ReviewNotificationProviderInterface;
use App\Service\Notification\User\ReviewNotificationService;
use ArrayObject;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ReviewNotificationServiceTest extends TestCase
{
    private function makeProvider(string $identifier, array $items): ReviewNotificationProviderInterface
    {
        $mock = $this->createMock(ReviewNotificationProviderInterface::class);
        $mock->method('getIdentifier')->willReturn($identifier);
        $mock->method('getReviewItems')->willReturn($items);

        return $mock;
    }

    private function makeItem(string $id = '1'): ReviewNotificationItem
    {
        return new ReviewNotificationItem(id: $id, description: 'Test');
    }

    public function testEmptyProvidersReturnsZeroCount(): void
    {
        // Arrange
        $service = new ReviewNotificationService(providers: new ArrayObject([]));
        $user = $this->createStub(User::class);

        // Act
        $count = $service->countForUser($user);

        // Assert
        static::assertSame(0, $count);
    }

    public function testCountSumsItemsAcrossAllProviders(): void
    {
        // Arrange
        $user = $this->createStub(User::class);
        $providers = [
            $this->makeProvider('a', [$this->makeItem('1'), $this->makeItem('2')]),
            $this->makeProvider('b', [$this->makeItem('3')]),
        ];
        $service = new ReviewNotificationService(providers: new ArrayObject($providers));

        // Act
        $count = $service->countForUser($user);

        // Assert
        static::assertSame(3, $count);
    }

    public function testGetProvidersForUserExcludesProvidersWithNoItems(): void
    {
        // Arrange
        $user = $this->createStub(User::class);
        $withItems = $this->makeProvider('with', [$this->makeItem()]);
        $empty = $this->makeProvider('empty', []);
        $service = new ReviewNotificationService(providers: new ArrayObject([$withItems, $empty]));

        // Act
        $result = $service->getProvidersForUser($user);

        // Assert
        static::assertCount(1, $result);
        static::assertSame('with', $result[0]['provider']->getIdentifier());
    }

    public function testGetProviderByIdentifierReturnsCorrectProvider(): void
    {
        // Arrange
        $providerA = $this->makeProvider('alpha', []);
        $providerB = $this->makeProvider('beta', []);
        $service = new ReviewNotificationService(providers: new ArrayObject([$providerA, $providerB]));

        // Act
        $found = $service->getProviderByIdentifier('beta');

        // Assert
        static::assertSame('beta', $found->getIdentifier());
    }

    public function testGetProviderByIdentifierThrowsOnUnknown(): void
    {
        // Arrange
        $service = new ReviewNotificationService(providers: new ArrayObject([]));

        // Assert
        $this->expectException(InvalidArgumentException::class);

        // Act
        $service->getProviderByIdentifier('nonexistent');
    }
}
