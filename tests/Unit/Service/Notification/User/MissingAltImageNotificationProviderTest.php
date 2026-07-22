<?php declare(strict_types=1);

namespace Tests\Unit\Service\Notification\User;

use App\Entity\Image;
use App\Entity\User;
use App\Repository\ImageRepository;
use App\Service\Config\LanguageService;
use App\Service\Media\AltLocaleRequirementResolver;
use App\Service\Notification\User\MissingAltImageNotificationProvider;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
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

        $provider = new MissingAltImageNotificationProvider(
            $imageRepo,
            $security,
            $this->createStub(TranslatorInterface::class),
            $this->createStub(LanguageService::class),
            $this->createStub(AltLocaleRequirementResolver::class),
        );

        // Act / Assert
        static::assertSame([], $provider->getNotifications($this->createStub(User::class)));
    }

    public function testSkipsImagesThatAreCompleteInEveryLocale(): void
    {
        // Arrange
        $security = $this->createStub(Security::class);
        $security->method('isGranted')->willReturn(true);

        $language = $this->createStub(LanguageService::class);
        $language->method('getFilteredDefaultLocale')->willReturn('en');

        $requirements = $this->createStub(AltLocaleRequirementResolver::class);
        $requirements->method('getRequiredAltLocales')->willReturn(['en', 'de']);

        // Every language has its own value (en -> base, de -> map), so nothing is missing.
        $complete = self::imageWithId(5);
        $complete->setAlt('english alt');
        $complete->setAltTranslation('de', 'deutscher alt');

        $imageRepo = $this->createStub(ImageRepository::class);
        $imageRepo->method('findHighUsageMissingAlt')->willReturn([['image' => $complete, 'count' => 9]]);

        $provider = new MissingAltImageNotificationProvider($imageRepo, $security, $this->createStub(TranslatorInterface::class), $language, $requirements);

        // Act / Assert
        static::assertSame([], $provider->getNotifications($this->createStub(User::class)));
    }

    public function testEmitsItemPerIncompleteImageWithMissingLanguageCount(): void
    {
        // Arrange
        $security = $this->createStub(Security::class);
        $security->method('isGranted')->willReturn(true);

        $language = $this->createStub(LanguageService::class);
        $language->method('getFilteredDefaultLocale')->willReturn('en');

        $requirements = $this->createStub(AltLocaleRequirementResolver::class);
        $requirements->method('getRequiredAltLocales')->willReturn(['en', 'de', 'zh']);

        $partly = self::imageWithId(11);
        $partly->setAlt('');
        $partly->setAltTranslation('de', 'de alt'); // missing en + zh => 2

        $empty = self::imageWithId(22); // missing en + de + zh => 3

        $imageRepo = $this->createStub(ImageRepository::class);
        $imageRepo
            ->method('findHighUsageMissingAlt')
            ->willReturn([
                ['image' => $partly, 'count' => 4],
                ['image' => $empty, 'count' => 7],
            ]);

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn(string $key, array $params) => $key . ':' . $params['%id%'] . ':' . $params['%missing%']);

        $provider = new MissingAltImageNotificationProvider($imageRepo, $security, $translator, $language, $requirements);

        // Act
        $items = $provider->getNotifications($this->createStub(User::class));

        // Assert
        static::assertCount(2, $items);
        static::assertSame('chrome.notification_image_missing_alt:11:2', $items[0]->label);
        static::assertSame('fa-image', $items[0]->icon);
        static::assertSame('app_admin_system_images_show', $items[0]->route);
        static::assertSame(['id' => 11], $items[0]->routeParams);
        static::assertSame('chrome.notification_image_missing_alt:22:3', $items[1]->label);
    }

    private static function imageWithId(int $id): Image
    {
        $image = new Image();
        $property = new ReflectionProperty(Image::class, 'id');
        $property->setValue($image, $id);

        return $image;
    }
}
