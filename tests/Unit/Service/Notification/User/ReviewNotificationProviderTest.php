<?php declare(strict_types=1);

namespace Tests\Unit\Service\Notification\User;

use App\Entity\User;
use App\Service\Notification\User\NotificationItem;
use App\Service\Notification\User\ReviewNotificationProvider;
use App\Service\Notification\User\ReviewNotificationService;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class ReviewNotificationProviderTest extends TestCase
{
    private function makeService(int $count): ReviewNotificationService
    {
        $mock = $this->createStub(ReviewNotificationService::class);
        $mock->method('countForUser')->willReturn($count);

        return $mock;
    }

    private function makeTranslator(): TranslatorInterface
    {
        $mock = $this->createStub(TranslatorInterface::class);
        $mock->method('trans')->willReturnCallback(
            static fn(string $id, array $params) => $params['%count%'] . ' items pending',
        );

        return $mock;
    }

    public function testZeroItemsProducesEmptyArray(): void
    {
        // Arrange
        $user = $this->createStub(User::class);
        $provider = new ReviewNotificationProvider($this->makeService(0), $this->makeTranslator());

        // Act
        $result = $provider->getNotifications($user);

        // Assert
        static::assertSame([], $result);
    }

    public function testNonZeroCountProducesExactlyOneNotificationItem(): void
    {
        // Arrange
        $user = $this->createStub(User::class);
        $provider = new ReviewNotificationProvider($this->makeService(3), $this->makeTranslator());

        // Act
        $result = $provider->getNotifications($user);

        // Assert
        static::assertCount(1, $result);
        static::assertInstanceOf(NotificationItem::class, $result[0]);
        static::assertSame('app_profile_review', $result[0]->route);
    }
}
