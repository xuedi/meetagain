<?php declare(strict_types=1);

namespace Module\Email\Tests\Unit;

use App\Entity\User;
use Module\Email\Internal\Notification\StaleQueueNotificationProvider;
use Module\Email\Internal\Repository\EmailQueueRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Translation\IdentityTranslator;

final class StaleQueueNotificationProviderTest extends TestCase
{
    public function testAnAdminSeesTheStaleCountLinkedToTheSendlog(): void
    {
        // Arrange
        $provider = $this->provider(stale: 2, isAdmin: true);

        // Act
        $items = $provider->getNotifications(new User());

        // Assert
        static::assertCount(1, $items);
        static::assertStringContainsString('chrome.notification_stale_emails', $items[0]->label);
        static::assertSame('app_admin_email_sendlog', $items[0]->route);
    }

    public function testNothingStaleShowsNothing(): void
    {
        // Arrange
        $provider = $this->provider(stale: 0, isAdmin: true);

        // Act
        $items = $provider->getNotifications(new User());

        // Assert
        static::assertSame([], $items);
    }

    public function testANonAdminNeverSeesTheItem(): void
    {
        // Arrange
        $provider = $this->provider(stale: 3, isAdmin: false);

        // Act
        $items = $provider->getNotifications(new User());

        // Assert
        static::assertSame([], $items);
    }

    private function provider(int $stale, bool $isAdmin): StaleQueueNotificationProvider
    {
        $repo = $this->createStub(EmailQueueRepository::class);
        $repo->method('getStaleCount')->willReturn($stale);
        $security = $this->createStub(Security::class);
        $security->method('isGranted')->willReturn($isAdmin);

        return new StaleQueueNotificationProvider($repo, $security, new IdentityTranslator());
    }
}
