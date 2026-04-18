<?php declare(strict_types=1);

namespace Tests\Unit\Service\Notification;

use App\Entity\User;
use App\Repository\EmailQueueRepository;
use App\Repository\SupportRequestRepository;
use App\Service\Notification\User\CoreNotificationProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

class CoreNotificationProviderTest extends TestCase
{
    private function createProvider(
        int $staleEmails = 0,
        int $newSupportRequests = 0,
        bool $isAdmin = true,
    ): CoreNotificationProvider {
        $emailRepoStub = $this->createStub(EmailQueueRepository::class);
        $emailRepoStub->method('getStaleCount')->willReturn($staleEmails);

        $supportRepoStub = $this->createStub(SupportRequestRepository::class);
        $supportRepoStub->method('getNewCount')->willReturn($newSupportRequests);

        $securityStub = $this->createStub(Security::class);
        $securityStub->method('isGranted')->willReturn($isAdmin);

        return new CoreNotificationProvider(
            emailRepo: $emailRepoStub,
            supportRequestRepo: $supportRepoStub,
            security: $securityStub,
        );
    }

    #[DataProvider('getNotificationsProvider')]
    public function testGetNotifications(
        bool $isAdmin,
        int $staleEmails,
        int $newSupportRequests,
        int $expectedCount,
        array $expectedLabelFragments,
    ): void {
        // Arrange
        $provider = $this->createProvider(
            staleEmails: $staleEmails,
            newSupportRequests: $newSupportRequests,
            isAdmin: $isAdmin,
        );

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
        yield 'non-admin user → empty array' => [
            'isAdmin' => false,
            'staleEmails' => 3,
            'newSupportRequests' => 1,
            'expectedCount' => 0,
            'expectedLabelFragments' => [],
        ];
        yield 'admin, nothing pending → empty array' => [
            'isAdmin' => true,
            'staleEmails' => 0,
            'newSupportRequests' => 0,
            'expectedCount' => 0,
            'expectedLabelFragments' => [],
        ];
        yield 'admin, 1 stale email → singular label' => [
            'isAdmin' => true,
            'staleEmails' => 1,
            'newSupportRequests' => 0,
            'expectedCount' => 1,
            'expectedLabelFragments' => ['1 Stale Email'],
        ];
        yield 'admin, 2 stale emails → plural label' => [
            'isAdmin' => true,
            'staleEmails' => 2,
            'newSupportRequests' => 0,
            'expectedCount' => 1,
            'expectedLabelFragments' => ['2 Stale Emails'],
        ];
        yield 'admin, 1 new support request → singular label' => [
            'isAdmin' => true,
            'staleEmails' => 0,
            'newSupportRequests' => 1,
            'expectedCount' => 1,
            'expectedLabelFragments' => ['1 New Support Request'],
        ];
        yield 'admin, 2 new support requests → plural label' => [
            'isAdmin' => true,
            'staleEmails' => 0,
            'newSupportRequests' => 2,
            'expectedCount' => 1,
            'expectedLabelFragments' => ['2 New Support Requests'],
        ];
        yield 'admin, stale emails and support requests → 2 items' => [
            'isAdmin' => true,
            'staleEmails' => 1,
            'newSupportRequests' => 1,
            'expectedCount' => 2,
            'expectedLabelFragments' => ['Stale Email', 'New Support Request'],
        ];
    }
}
