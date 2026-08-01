<?php declare(strict_types=1);

namespace Plugin\Photos\Tests\Unit\Comment;

use App\Activity\ActivityService;
use App\Entity\Comment;
use App\Entity\User;
use PHPUnit\Framework\TestCase;
use Plugin\Photos\Activity\Messages\CommentedOnPhoto;
use Plugin\Photos\Comment\PhotoTargetProvider;
use Plugin\Photos\Entity\Photo;
use Plugin\Photos\Entity\PhotoTranslation;
use Plugin\Photos\Service\PhotoService;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class PhotoTargetProviderTest extends TestCase
{
    public function testClaimsThePhotoItemType(): void
    {
        // Act + Assert
        static::assertSame('photo', $this->provider()->getTypeKey());
    }

    public function testTheReturnUrlIsTheDetailPage(): void
    {
        // Arrange
        $provider = $this->provider(photo: $this->photo());

        // Act + Assert
        static::assertSame('/en/photos/4', $provider->getReturnUrl(4));
    }

    public function testAPhotoOutsideTheVisibleSetHasNoReturnUrl(): void
    {
        // Act + Assert
        static::assertNull($this->provider()->getReturnUrl(4));
    }

    public function testOnlyASignedInVisitorMayCommentOnAVisiblePhoto(): void
    {
        // Act + Assert
        static::assertTrue($this->provider(photo: $this->photo(), granted: true)->canComment(4));
        static::assertFalse($this->provider(photo: $this->photo(), granted: false)->canComment(4));
        static::assertFalse($this->provider(granted: true)->canComment(4));
    }

    public function testTheCreatedHookLogsTheActivityWithTheTitle(): void
    {
        // Arrange
        $activity = $this->createMock(ActivityService::class);
        $activity->expects(static::once())
            ->method('log')
            ->with(CommentedOnPhoto::TYPE, static::anything(), ['photo_id' => 4, 'photo_title' => 'Harbour']);
        $provider = $this->provider(photo: $this->photo(), activity: $activity);

        // Act
        $provider->onCommentCreated($this->comment($this->createStub(User::class)));
    }

    public function testTheCreatedHookIsSilentForAVanishedAuthor(): void
    {
        // Arrange
        $activity = $this->createMock(ActivityService::class);
        $activity->expects(static::never())->method('log');
        $provider = $this->provider(photo: $this->photo(), activity: $activity);

        // Act
        $provider->onCommentCreated($this->comment(null));
    }

    private function provider(?Photo $photo = null, bool $granted = true, ?ActivityService $activity = null): PhotoTargetProvider
    {
        $photoService = $this->createStub(PhotoService::class);
        $photoService->method('get')->willReturn($photo);

        $checker = $this->createStub(AuthorizationCheckerInterface::class);
        $checker->method('isGranted')->willReturn($granted);

        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturnCallback(
            static fn(string $route, array $params = []): string => '/en/photos/' . $params['id'],
        );

        return new PhotoTargetProvider($photoService, $checker, $urlGenerator, $activity ?? $this->createStub(ActivityService::class));
    }

    private function photo(): Photo
    {
        $photo = new Photo();
        $photo->addTranslation(new PhotoTranslation()->setLanguage('en')->setTitle('Harbour'));

        return $photo;
    }

    private function comment(?User $user): Comment
    {
        $comment = new Comment();
        $comment->setTargetType('photo');
        $comment->setTargetId(4);
        if ($user !== null) {
            $comment->setUser($user);
        }

        return $comment;
    }
}
