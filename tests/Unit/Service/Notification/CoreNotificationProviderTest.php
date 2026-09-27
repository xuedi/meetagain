<?php declare(strict_types=1);

namespace Tests\Unit\Service\Notification;

use App\Entity\User;
use App\Service\Notification\User\CoreNotificationProvider;
use App\Service\Support\VisibilityResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Translation\IdentityTranslator;

class CoreNotificationProviderTest extends TestCase
{
    private function createProvider(int $newSupportRequests = 0, bool $isAdmin = true): CoreNotificationProvider
    {
        $visibilityStub = $this->createStub(VisibilityResolver::class);
        $visibilityStub->method('countNew')->willReturn($newSupportRequests);

        $securityStub = $this->createStub(Security::class);
        $securityStub->method('isGranted')->willReturn($isAdmin);

        return new CoreNotificationProvider(visibilityResolver: $visibilityStub, security: $securityStub, translator: new IdentityTranslator());
    }

    #[DataProvider('getNotificationsProvider')]
    public function testGetNotifications(bool $isAdmin, int $newSupportRequests, int $expectedCount, array $expectedLabelFragments): void
    {
        // Arrange
        $provider = $this->createProvider(newSupportRequests: $newSupportRequests, isAdmin: $isAdmin);

        // Act
        $result = $provider->getNotifications($this->createStub(User::class));

        // Assert
        static::assertCount($expectedCount, $result);
        foreach ($expectedLabelFragments as $i => $fragment) {
            static::assertStringContainsString($fragment, $result[$i]->label);
        }
    }

    public static function getNotificationsProvider(): iterable
    {
        yield 'user below steward → empty array' => [
            'isAdmin' => false,
            'newSupportRequests' => 1,
            'expectedCount' => 0,
            'expectedLabelFragments' => [],
        ];
        yield 'admin, nothing pending → empty array' => [
            'isAdmin' => true,
            'newSupportRequests' => 0,
            'expectedCount' => 0,
            'expectedLabelFragments' => [],
        ];
        yield 'admin, 1 new support request → support-requests key' => [
            'isAdmin' => true,
            'newSupportRequests' => 1,
            'expectedCount' => 1,
            'expectedLabelFragments' => ['chrome.notification_new_support_requests'],
        ];
        yield 'admin, 2 new support requests → support-requests key' => [
            'isAdmin' => true,
            'newSupportRequests' => 2,
            'expectedCount' => 1,
            'expectedLabelFragments' => ['chrome.notification_new_support_requests'],
        ];
    }
}
