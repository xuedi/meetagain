<?php declare(strict_types=1);

namespace Plugin\Photos\Tests\Unit\Member;

use App\Entity\User;
use App\Item\ListRegistry;
use App\Publisher\PluginSettings\Resolver;
use PHPUnit\Framework\TestCase;
use Plugin\Photos\Entity\Photo;
use Plugin\Photos\Member\StreamSectionProvider;
use Plugin\Photos\Service\ConfigService;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Plugin\Photos\Service\PhotoService;
use Plugin\Photos\ValueObject\Config;
use Twig\Environment;

class StreamSectionProviderTest extends TestCase
{
    public function testTheSectionIsSkippedWhileThePhotoListIsNotActiveHere(): void
    {
        // Arrange
        $provider = $this->provider(listed: false);

        // Act + Assert
        static::assertNull($provider->renderSection($this->user(1), $this->user(7)));
    }

    public function testTheSectionIsSkippedWhileTheStreamSettingIsOff(): void
    {
        // Arrange
        $provider = $this->provider(streams: false);

        // Act + Assert
        static::assertNull($provider->renderSection($this->user(1), $this->user(7)));
    }

    public function testTheSectionIsSkippedForAMemberWithoutAPhotoInScope(): void
    {
        // Arrange
        $provider = $this->provider(photos: []);

        // Act + Assert
        static::assertNull($provider->renderSection($this->user(1), $this->user(7)));
    }

    public function testTheSectionIsSkippedForAnUnsavedTarget(): void
    {
        // Arrange
        $provider = $this->provider();

        // Act + Assert
        static::assertNull($provider->renderSection($this->user(1), $this->createStub(User::class)));
    }

    public function testTheSectionRendersWhenTheListTheSettingAndThePhotosAllHold(): void
    {
        // Arrange
        $provider = $this->provider();

        // Act + Assert
        static::assertSame('<section>', $provider->renderSection($this->user(1), $this->user(7)));
    }

    /** @param list<Photo>|null $photos */
    private function provider(bool $listed = true, bool $streams = true, ?array $photos = null): StreamSectionProvider
    {
        $registry = $this->createStub(ListRegistry::class);
        $registry->method('has')->willReturn($listed);

        $resolver = $this->createStub(Resolver::class);
        $resolver->method('resolve')->willReturn(new Config()->setMemberStreams($streams));

        $photoService = $this->createStub(PhotoService::class);
        $photoService->method('getStream')->willReturn($photos ?? [new Photo()]);

        $twig = $this->createStub(Environment::class);
        $twig->method('render')->willReturn('<section>');

        return new StreamSectionProvider($photoService, new ConfigService($resolver, $this->createStub(AuthorizationCheckerInterface::class)), $registry, $twig);
    }

    private function user(int $id): User
    {
        $user = $this->createStub(User::class);
        $user->method('getId')->willReturn($id);

        return $user;
    }
}
