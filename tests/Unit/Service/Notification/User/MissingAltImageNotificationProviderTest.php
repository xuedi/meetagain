<?php declare(strict_types=1);

namespace Tests\Unit\Service\Notification\User;

use App\Entity\User;
use App\Repository\ImageRepository;
use App\Service\Notification\User\MissingAltImageNotificationProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Contracts\Translation\TranslatorInterface;

class MissingAltImageNotificationProviderTest extends TestCase
{
    public function testReturnsEmptyForNonAdmin(): void
    {
        // Arrange
        $security = $this->createStub(Security::class);
        $security->method('isGranted')->willReturn(false);

        $imageRepo = $this->createMock(ImageRepository::class);
        $imageRepo->expects($this->never())->method('findHighUsageMissingAlt');

        $provider = new MissingAltImageNotificationProvider($imageRepo, $security, $this->createStub(TranslatorInterface::class));

        // Act / Assert
        static::assertSame([], $provider->getNotifications($this->createStub(User::class)));
    }

    public function testReturnsEmptyArrayWhenNoMissingAltImages(): void
    {
        // Arrange
        $security = $this->createStub(Security::class);
        $security->method('isGranted')->willReturn(true);

        $imageRepo = $this->createStub(ImageRepository::class);
        $imageRepo->method('findHighUsageMissingAlt')->willReturn([]);

        $provider = new MissingAltImageNotificationProvider($imageRepo, $security, $this->createStub(TranslatorInterface::class));

        // Act / Assert
        static::assertSame([], $provider->getNotifications($this->createStub(User::class)));
    }

    public function testReturnsNotificationItemPerRow(): void
    {
        // Arrange
        $security = $this->createStub(Security::class);
        $security->method('isGranted')->willReturn(true);

        $imageRepo = $this->createStub(ImageRepository::class);
        $imageRepo
            ->method('findHighUsageMissingAlt')
            ->willReturn([
                ['id' => 11, 'count' => 4],
                ['id' => 22, 'count' => 7],
            ]);

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn(string $key, array $params) => $key . ':' . $params['%id%'] . ':' . $params['%count%']);

        $provider = new MissingAltImageNotificationProvider($imageRepo, $security, $translator);

        // Act
        $items = $provider->getNotifications($this->createStub(User::class));

        // Assert
        static::assertCount(2, $items);
        static::assertSame('chrome.notification_image_missing_alt:11:4', $items[0]->label);
        static::assertSame('fa-image', $items[0]->icon);
        static::assertSame('app_admin_system_images_show', $items[0]->route);
        static::assertSame(['id' => 11], $items[0]->routeParams);
        static::assertSame('chrome.notification_image_missing_alt:22:7', $items[1]->label);
    }
}
