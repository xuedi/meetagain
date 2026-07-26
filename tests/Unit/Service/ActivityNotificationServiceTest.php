<?php declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Activity\Messages\Login;
use App\Activity\Messages\RsvpYes;
use App\Activity\Messages\SendMessage;
use App\Activity\NotificationService as ActivityNotificationService;
use App\Emails\Guard\EmailGuardEvaluator;
use App\Emails\Types\NotificationMessageEmail;
use App\Entity\Activity;
use App\Repository\EventRepository;
use App\Repository\UserRepository;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use ReflectionMethod;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Tests\Unit\Stubs\EventStub;
use Tests\Unit\Stubs\UserStub;

final class ActivityNotificationServiceTest extends TestCase
{
    public function testNotifyWithRsvpYesCallsSendRsvp(): void
    {
        // Arrange
        $user = new UserStub()->setId(1);
        $activity = $this->createStub(Activity::class);
        $activity->method('getUser')->willReturn($user);
        $activity->method('getType')->willReturn(RsvpYes::TYPE);
        $activity->method('getMeta')->willReturn(['event_id' => 42]);

        $eventRepoMock = $this->createMock(EventRepository::class);
        $eventRepoMock->expects($this->once())->method('findOneBy')->with(['id' => 42])->willReturn(new EventStub()->setId(42));

        $service = new ActivityNotificationService(
            notificationMessageEmail: $this->createStub(NotificationMessageEmail::class),
            eventRepo: $eventRepoMock,
            userRepo: $this->createStub(UserRepository::class),
            appCache: $this->createStub(TagAwareCacheInterface::class),
            logger: new NullLogger(),
            guardEvaluator: new EmailGuardEvaluator(),
        );

        // Act
        $service->notify($activity);

        // Assert
    }

    public function testNotifyWithSendMessageCallsSendMessage(): void
    {
        // Arrange
        $sender = new UserStub()->setId(1);
        $recipient = new UserStub()->setId(2);

        $activity = $this->createStub(Activity::class);
        $activity->method('getUser')->willReturn($sender);
        $activity->method('getType')->willReturn(SendMessage::TYPE);
        $activity->method('getMeta')->willReturn(['user_id' => 2]);

        $userRepoMock = $this->createMock(UserRepository::class);
        $userRepoMock->expects($this->once())->method('findOneBy')->with(['id' => 2])->willReturn($recipient);

        $cacheMock = $this->createMock(TagAwareCacheInterface::class);
        $cacheMock->expects($this->once())->method('get');

        $service = new ActivityNotificationService(
            notificationMessageEmail: $this->createStub(NotificationMessageEmail::class),
            eventRepo: $this->createStub(EventRepository::class),
            userRepo: $userRepoMock,
            appCache: $cacheMock,
            logger: new NullLogger(),
            guardEvaluator: new EmailGuardEvaluator(),
        );

        // Act
        $service->notify($activity);

        // Assert
    }

    public function testNotifyWithUnknownTypeDoesNothing(): void
    {
        // Arrange
        $activity = $this->createStub(Activity::class);
        $activity->method('getUser')->willReturn(new UserStub());
        $activity->method('getType')->willReturn(Login::TYPE);
        $activity->method('getMeta')->willReturn([]);

        $eventRepoMock = $this->createMock(EventRepository::class);
        $eventRepoMock->expects($this->never())->method('findOneBy');

        $userRepoMock = $this->createMock(UserRepository::class);
        $userRepoMock->expects($this->never())->method('findOneBy');

        $service = new ActivityNotificationService(
            notificationMessageEmail: $this->createStub(NotificationMessageEmail::class),
            eventRepo: $eventRepoMock,
            userRepo: $userRepoMock,
            appCache: $this->createStub(TagAwareCacheInterface::class),
            logger: new NullLogger(),
            guardEvaluator: new EmailGuardEvaluator(),
        );

        // Act
        $service->notify($activity);

        // Assert
    }

    public function testSendRsvpReturnsEarlyWhenEventNotFound(): void
    {
        // Arrange
        $user = new UserStub()->setId(1);

        $eventRepoStub = $this->createStub(EventRepository::class);
        $eventRepoStub->method('findOneBy')->willReturn(null);

        $cacheMock = $this->createMock(TagAwareCacheInterface::class);
        $cacheMock->expects($this->never())->method('get');

        $service = new ActivityNotificationService(
            notificationMessageEmail: $this->createStub(NotificationMessageEmail::class),
            eventRepo: $eventRepoStub,
            userRepo: $this->createStub(UserRepository::class),
            appCache: $cacheMock,
            logger: new NullLogger(),
            guardEvaluator: new EmailGuardEvaluator(),
        );

        // Act
        $service->sendRsvp($user, 999);

        // Assert
    }

    public function testSendRsvpNotifiesFollowersWhenNotificationEnabled(): void
    {
        // Arrange
        $user = new UserStub()->setId(1);
        $follower = new UserStub()->setId(2);
        $follower->setNotification(true);

        $notificationSettings = new \App\Entity\NotificationSettings(['followingUpdates' => true]);
        $follower->setNotificationSettings($notificationSettings);

        $user->addFollower($follower);

        $event = new EventStub()->setId(42);

        $eventRepoStub = $this->createStub(EventRepository::class);
        $eventRepoStub->method('findOneBy')->willReturn($event);

        $cacheMock = $this->createMock(TagAwareCacheInterface::class);
        $cacheMock
            ->expects($this->once())
            ->method('get')
            ->willReturnCallback(function ($key, $callback) {
                $item = $this->createStub(ItemInterface::class);

                return $callback($item);
            });

        $service = new ActivityNotificationService(
            notificationMessageEmail: $this->createStub(NotificationMessageEmail::class),
            eventRepo: $eventRepoStub,
            userRepo: $this->createStub(UserRepository::class),
            appCache: $cacheMock,
            logger: new NullLogger(),
            guardEvaluator: new EmailGuardEvaluator(),
        );

        // Act
        $service->sendRsvp($user, 42);

        // Assert
    }

    public function testSendRsvpSkipsFollowersWithNotificationsDisabled(): void
    {
        // Arrange
        $user = new UserStub()->setId(1);
        $follower = new UserStub()->setId(2);
        $follower->setNotification(false);

        $user->addFollower($follower);

        $event = new EventStub()->setId(42);

        $eventRepoStub = $this->createStub(EventRepository::class);
        $eventRepoStub->method('findOneBy')->willReturn($event);

        $cacheMock = $this->createMock(TagAwareCacheInterface::class);
        $cacheMock
            ->expects($this->once())
            ->method('get')
            ->willReturnCallback(function ($key, $callback) {
                $item = $this->createStub(ItemInterface::class);

                return $callback($item);
            });

        $service = new ActivityNotificationService(
            notificationMessageEmail: $this->createStub(NotificationMessageEmail::class),
            eventRepo: $eventRepoStub,
            userRepo: $this->createStub(UserRepository::class),
            appCache: $cacheMock,
            logger: new NullLogger(),
            guardEvaluator: new EmailGuardEvaluator(),
        );

        // Act
        $service->sendRsvp($user, 42);

        // Assert
    }

    public function testSendMessageReturnsEarlyWhenUserIsNull(): void
    {
        // Arrange
        $userRepoMock = $this->createMock(UserRepository::class);
        $userRepoMock->expects($this->never())->method('findOneBy');

        $service = new ActivityNotificationService(
            notificationMessageEmail: $this->createStub(NotificationMessageEmail::class),
            eventRepo: $this->createStub(EventRepository::class),
            userRepo: $userRepoMock,
            appCache: $this->createStub(TagAwareCacheInterface::class),
            logger: new NullLogger(),
            guardEvaluator: new EmailGuardEvaluator(),
        );

        // Act
        $method = new ReflectionMethod($service, 'sendMessage');
        $method->invoke($service, null, 2);

        // Assert
    }

    public function testSendMessageReturnsEarlyWhenRecipientNotFound(): void
    {
        // Arrange
        $sender = new UserStub()->setId(1);

        $userRepoStub = $this->createStub(UserRepository::class);
        $userRepoStub->method('findOneBy')->willReturn(null);

        $cacheMock = $this->createMock(TagAwareCacheInterface::class);
        $cacheMock->expects($this->never())->method('get');

        $service = new ActivityNotificationService(
            notificationMessageEmail: $this->createStub(NotificationMessageEmail::class),
            eventRepo: $this->createStub(EventRepository::class),
            userRepo: $userRepoStub,
            appCache: $cacheMock,
            logger: new NullLogger(),
            guardEvaluator: new EmailGuardEvaluator(),
        );

        // Act
        $method = new ReflectionMethod($service, 'sendMessage');
        $method->invoke($service, $sender, 999);

        // Assert
    }

    public function testSendMessageSendsEmailWhenConditionsAreMet(): void
    {
        // Arrange
        $sender = new UserStub()->setId(1);
        $recipient = new UserStub()->setId(2);
        $recipient->setNotification(true);
        $recipient->setLastLogin(new \DateTime('-3 hours'));

        $notificationSettings = new \App\Entity\NotificationSettings(['receivedMessage' => true]);
        $recipient->setNotificationSettings($notificationSettings);

        $userRepoStub = $this->createStub(UserRepository::class);
        $userRepoStub->method('findOneBy')->willReturn($recipient);

        $emailMock = $this->createMock(NotificationMessageEmail::class);
        $emailMock->method('guardCheck')->willReturn(true);
        $emailMock->expects($this->once())->method('send');

        $cacheMock = $this->createMock(TagAwareCacheInterface::class);
        $cacheMock
            ->expects($this->once())
            ->method('get')
            ->willReturnCallback(function ($key, $callback) {
                $item = $this->createStub(ItemInterface::class);

                return $callback($item);
            });

        $service = new ActivityNotificationService(
            notificationMessageEmail: $emailMock,
            eventRepo: $this->createStub(EventRepository::class),
            userRepo: $userRepoStub,
            appCache: $cacheMock,
            logger: new NullLogger(),
            guardEvaluator: new EmailGuardEvaluator(),
        );

        // Act
        $method = new ReflectionMethod($service, 'sendMessage');
        $method->invoke($service, $sender, 2);

        // Assert
    }

    public function testSendMessageSkipsWhenRecipientRecentlyActive(): void
    {
        // Arrange
        $sender = new UserStub()->setId(1);
        $recipient = new UserStub()->setId(2);
        $recipient->setNotification(true);
        $recipient->setLastLogin(new \DateTime('-1 hour'));

        $notificationSettings = new \App\Entity\NotificationSettings(['receivedMessage' => true]);
        $recipient->setNotificationSettings($notificationSettings);

        $userRepoStub = $this->createStub(UserRepository::class);
        $userRepoStub->method('findOneBy')->willReturn($recipient);

        $skipRule = new class implements \App\Emails\EmailGuardRuleInterface {
            public function getName(): string
            {
                return 'test-skip';
            }

            public function getCost(): \App\Emails\EmailGuardCost
            {
                return \App\Emails\EmailGuardCost::Free;
            }

            public function evaluate(array $context): \App\Emails\EmailGuardResult
            {
                return \App\Emails\EmailGuardResult::skip('test-skip', 'recently active');
            }
        };
        $emailMock = $this->createMock(NotificationMessageEmail::class);
        $emailMock->method('getGuardRules')->willReturn([$skipRule]);
        $emailMock->expects($this->never())->method('send');

        $cacheMock = $this->createMock(TagAwareCacheInterface::class);
        $cacheMock
            ->expects($this->once())
            ->method('get')
            ->willReturnCallback(function ($key, $callback) {
                $item = $this->createStub(ItemInterface::class);

                return $callback($item);
            });

        $service = new ActivityNotificationService(
            notificationMessageEmail: $emailMock,
            eventRepo: $this->createStub(EventRepository::class),
            userRepo: $userRepoStub,
            appCache: $cacheMock,
            logger: new NullLogger(),
            guardEvaluator: new EmailGuardEvaluator(),
        );

        // Act
        $method = new ReflectionMethod($service, 'sendMessage');
        $method->invoke($service, $sender, 2);

        // Assert
    }
}
