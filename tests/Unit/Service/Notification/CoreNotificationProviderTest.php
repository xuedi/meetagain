<?php declare(strict_types=1);

namespace Tests\Unit\Service\Notification;

use App\Entity\User;
use App\Repository\EmailQueueRepository;
use App\Repository\ImageReportRepository;
use App\Repository\SupportRequestRepository;
use App\Repository\UserRepository;
use App\Service\Notification\User\CoreNotificationProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

class CoreNotificationProviderTest extends TestCase
{
    private function createProvider(
        int $openReports = 0,
        int $staleEmails = 0,
        int $pendingApproval = 0,
        int $newSupportRequests = 0,
        bool $isAdmin = true,
    ): CoreNotificationProvider {
        $imageRepoStub = $this->createStub(ImageReportRepository::class);
        $imageRepoStub->method('getOpenCount')->willReturn($openReports);

        $emailRepoStub = $this->createStub(EmailQueueRepository::class);
        $emailRepoStub->method('getStaleCount')->willReturn($staleEmails);

        $userRepoStub = $this->createStub(UserRepository::class);
        $userRepoStub->method('getUnverifiedCount')->willReturn($pendingApproval);

        $supportRepoStub = $this->createStub(SupportRequestRepository::class);
        $supportRepoStub->method('getNewCount')->willReturn($newSupportRequests);

        $securityStub = $this->createStub(Security::class);
        $securityStub->method('isGranted')->willReturn($isAdmin);

        return new CoreNotificationProvider(
            imageReportRepo: $imageRepoStub,
            emailRepo: $emailRepoStub,
            userRepo: $userRepoStub,
            supportRequestRepo: $supportRepoStub,
            security: $securityStub,
        );
    }

    #[DataProvider('getNotificationsProvider')]
    public function testGetNotifications(
        bool $isAdmin,
        int $openReports,
        int $staleEmails,
        int $pendingApproval,
        int $newSupportRequests,
        int $expectedCount,
        array $expectedLabelFragments,
    ): void {
        // Arrange
        $provider = $this->createProvider(
            openReports: $openReports,
            staleEmails: $staleEmails,
            pendingApproval: $pendingApproval,
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
            'openReports' => 5,
            'staleEmails' => 3,
            'pendingApproval' => 2,
            'newSupportRequests' => 1,
            'expectedCount' => 0,
            'expectedLabelFragments' => [],
        ];
        yield 'admin, nothing pending → empty array' => [
            'isAdmin' => true,
            'openReports' => 0, 'staleEmails' => 0,
            'pendingApproval' => 0, 'newSupportRequests' => 0,
            'expectedCount' => 0,
            'expectedLabelFragments' => [],
        ];
        yield 'admin, 1 open report → singular label' => [
            'isAdmin' => true,
            'openReports' => 1, 'staleEmails' => 0,
            'pendingApproval' => 0, 'newSupportRequests' => 0,
            'expectedCount' => 1,
            'expectedLabelFragments' => ['1 Reported Image'],
        ];
        yield 'admin, 3 open reports → plural label' => [
            'isAdmin' => true,
            'openReports' => 3, 'staleEmails' => 0,
            'pendingApproval' => 0, 'newSupportRequests' => 0,
            'expectedCount' => 1,
            'expectedLabelFragments' => ['3 Reported Images'],
        ];
        yield 'admin, 1 stale email → singular label' => [
            'isAdmin' => true,
            'openReports' => 0, 'staleEmails' => 1,
            'pendingApproval' => 0, 'newSupportRequests' => 0,
            'expectedCount' => 1,
            'expectedLabelFragments' => ['1 Stale Email'],
        ];
        yield 'admin, 2 stale emails → plural label' => [
            'isAdmin' => true,
            'openReports' => 0, 'staleEmails' => 2,
            'pendingApproval' => 0, 'newSupportRequests' => 0,
            'expectedCount' => 1,
            'expectedLabelFragments' => ['2 Stale Emails'],
        ];
        yield 'admin, 1 pending approval' => [
            'isAdmin' => true,
            'openReports' => 0, 'staleEmails' => 0,
            'pendingApproval' => 1, 'newSupportRequests' => 0,
            'expectedCount' => 1,
            'expectedLabelFragments' => ['1 Pending Approval'],
        ];
        yield 'admin, 1 new support request → singular label' => [
            'isAdmin' => true,
            'openReports' => 0, 'staleEmails' => 0,
            'pendingApproval' => 0, 'newSupportRequests' => 1,
            'expectedCount' => 1,
            'expectedLabelFragments' => ['1 New Support Request'],
        ];
        yield 'admin, 2 new support requests → plural label' => [
            'isAdmin' => true,
            'openReports' => 0, 'staleEmails' => 0,
            'pendingApproval' => 0, 'newSupportRequests' => 2,
            'expectedCount' => 1,
            'expectedLabelFragments' => ['2 New Support Requests'],
        ];
        yield 'admin, all counters non-zero → 4 items' => [
            'isAdmin' => true,
            'openReports' => 1, 'staleEmails' => 1,
            'pendingApproval' => 1, 'newSupportRequests' => 1,
            'expectedCount' => 4,
            'expectedLabelFragments' => [
                'Reported Image', 'Stale Email', 'Pending Approval', 'New Support Request',
            ],
        ];
    }
}
