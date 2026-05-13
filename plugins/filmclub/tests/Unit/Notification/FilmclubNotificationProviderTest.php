<?php declare(strict_types=1);

namespace Plugin\Filmclub\Tests\Unit\Notification;

use App\Activity\ActivityService;
use App\Entity\User;
use App\Service\Notification\User\NotificationItem;
use PHPUnit\Framework\TestCase;
use Plugin\Filmclub\Entity\FilmPoll;
use Plugin\Filmclub\Notification\FilmclubNotificationProvider;
use Plugin\Filmclub\Service\FilmService;
use Plugin\Filmclub\Service\PollService;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Contracts\Translation\TranslatorInterface;

class FilmclubNotificationProviderTest extends TestCase
{
    public function testGetIdentifierReturnsExpectedString(): void
    {
        // Arrange
        $provider = $this->makeProvider();

        // Act
        $result = $provider->getIdentifier();

        // Assert
        static::assertSame('filmclub.film_approval', $result);
    }

    public function testGetNotificationsReturnsEmptyWhenNoActivePolls(): void
    {
        // Arrange
        $pollService = $this->createStub(PollService::class);
        $pollService->method('getActivePolls')->willReturn([]);

        $user = $this->createStub(User::class);

        $provider = $this->makeProvider(pollService: $pollService);

        // Act
        $result = $provider->getNotifications($user);

        // Assert
        static::assertSame([], $result);
    }

    public function testGetNotificationsReturnsItemWhenUserHasNotVoted(): void
    {
        // Arrange
        $poll = $this->createStub(FilmPoll::class);
        $poll->method('getId')->willReturn(1);

        $pollService = $this->createStub(PollService::class);
        $pollService->method('getActivePolls')->willReturn([$poll]);
        $pollService->method('hasUserVoted')->willReturn(false);

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturn('Cast your vote');

        $user = $this->createStub(User::class);
        $user->method('getId')->willReturn(42);

        $provider = $this->makeProvider(pollService: $pollService, translator: $translator);

        // Act
        $result = $provider->getNotifications($user);

        // Assert
        static::assertCount(1, $result);
        static::assertInstanceOf(NotificationItem::class, $result[0]);
    }

    public function testGetNotificationsReturnsEmptyWhenUserAlreadyVoted(): void
    {
        // Arrange
        $poll = $this->createStub(FilmPoll::class);
        $poll->method('getId')->willReturn(1);

        $pollService = $this->createStub(PollService::class);
        $pollService->method('getActivePolls')->willReturn([$poll]);
        $pollService->method('hasUserVoted')->willReturn(true);

        $user = $this->createStub(User::class);
        $user->method('getId')->willReturn(42);

        $provider = $this->makeProvider(pollService: $pollService);

        // Act
        $result = $provider->getNotifications($user);

        // Assert
        static::assertSame([], $result);
    }

    public function testGetReviewItemsReturnsEmptyWhenNotOrganizer(): void
    {
        // Arrange
        $security = $this->createStub(Security::class);
        $security->method('isGranted')->willReturn(false);

        $user = $this->createStub(User::class);

        $provider = $this->makeProvider(security: $security);

        // Act
        $result = $provider->getReviewItems($user);

        // Assert
        static::assertSame([], $result);
    }

    public function testApproveItemThrowsWhenNotOrganizer(): void
    {
        // Arrange
        $security = $this->createStub(Security::class);
        $security->method('isGranted')->willReturn(false);

        $user = $this->createStub(User::class);

        $provider = $this->makeProvider(security: $security);

        // Assert
        $this->expectException(AccessDeniedException::class);

        // Act
        $provider->approveItem($user, '1');
    }

    private function makeProvider(
        ?PollService $pollService = null,
        ?FilmService $filmService = null,
        ?ActivityService $activityService = null,
        ?Security $security = null,
        ?TranslatorInterface $translator = null,
    ): FilmclubNotificationProvider {
        return new FilmclubNotificationProvider(
            pollService: $pollService ?? $this->createStub(PollService::class),
            filmService: $filmService ?? $this->createStub(FilmService::class),
            activityService: $activityService ?? $this->createStub(ActivityService::class),
            security: $security ?? $this->createStub(Security::class),
            translator: $translator ?? $this->createStub(TranslatorInterface::class),
        );
    }
}
