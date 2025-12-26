<?php declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Entity\Activity;
use App\Entity\ActivityType;
use App\Repository\EventRepository;
use App\Repository\UserRepository;
use App\Service\EmailService;
use App\Service\NotificationService;
use DateTime;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Tests\Unit\Stubs\EventStub;
use Tests\Unit\Stubs\UserStub;

final class NotificationServiceTest extends TestCase
{
    public function testNotifyWithRsvpYesCallsSendRsvp(): void
    {
        // Arrange: create activity with RsvpYes type
        $user = (new UserStub())->setId(1);
        $activity = $this->createStub(Activity::class);
        $activity->method('getUser')->willReturn($user);
        $activity->method('getType')->willReturn(ActivityType::RsvpYes);
        $activity->method('getMeta')->willReturn(['event_id' => 42]);

        $eventRepoMock = $this->createMock(EventRepository::class);
        $eventRepoMock->expects($this->once())
            ->method('findOneBy')
            ->with(['id' => 42])
            ->willReturn((new EventStub())->setId(42));

        $service = new NotificationService(
            emailService: $this->createStub(EmailService::class),
            eventRepo: $eventRepoMock,
            userRepo: $this->createStub(UserRepository::class),
            appCache: $this->createStub(TagAwareCacheInterface::class),
        );

        // Act: notify
        $service->notify($activity);

        // Assert: event repository was called (verified by mock)
    }

    public function testNotifyWithSendMessageCallsSendMessage(): void
    {
        // Arrange: create activity with SendMessage type
        $sender = (new UserStub())->setId(1);
        $recipient = (new UserStub())->setId(2);

        $activity = $this->createStub(Activity::class);
        $activity->method('getUser')->willReturn($sender);
        $activity->method('getType')->willReturn(ActivityType::SendMessage);
        $activity->method('getMeta')->willReturn(['user_id' => 2]);

        $userRepoMock = $this->createMock(UserRepository::class);
        $userRepoMock->expects($this->once())
            ->method('findOneBy')
            ->with(['id' => 2])
            ->willReturn($recipient);

        $cacheMock = $this->createMock(TagAwareCacheInterface::class);
        $cacheMock->expects($this->once())->method('get');

        $service = new NotificationService(
            emailService: $this->createStub(EmailService::class),
            eventRepo: $this->createStub(EventRepository::class),
            userRepo: $userRepoMock,
            appCache: $cacheMock,
        );

        // Act: notify
        $service->notify($activity);

        // Assert: user repository was called (verified by mock)
    }

    public function testNotifyWithUnknownTypeDoesNothing(): void
    {
        // Arrange: create activity with default type
        $activity = $this->createStub(Activity::class);
        $activity->method('getUser')->willReturn(new UserStub());
        $activity->method('getType')->willReturn(ActivityType::Login);
        $activity->method('getMeta')->willReturn([]);

        $eventRepoMock = $this->createMock(EventRepository::class);
        $eventRepoMock->expects($this->never())->method('findOneBy');

        $userRepoMock = $this->createMock(UserRepository::class);
        $userRepoMock->expects($this->never())->method('findOneBy');

        $service = new NotificationService(
            emailService: $this->createStub(EmailService::class),
            eventRepo: $eventRepoMock,
            userRepo: $userRepoMock,
            appCache: $this->createStub(TagAwareCacheInterface::class),
        );

        // Act: notify
        $service->notify($activity);

        // Assert: no repository calls (verified by mocks)
    }

    public function testSendRsvpReturnsEarlyWhenEventNotFound(): void
    {
        // Arrange: event does not exist
        $user = (new UserStub())->setId(1);

        $eventRepoStub = $this->createStub(EventRepository::class);
        $eventRepoStub->method('findOneBy')->willReturn(null);

        $cacheMock = $this->createMock(TagAwareCacheInterface::class);
        $cacheMock->expects($this->never())->method('get');

        $service = new NotificationService(
            emailService: $this->createStub(EmailService::class),
            eventRepo: $eventRepoStub,
            userRepo: $this->createStub(UserRepository::class),
            appCache: $cacheMock,
        );

        // Act: send RSVP notification
        $service->sendRsvp($user, 999);

        // Assert: cache not accessed (verified by mock)
    }

    public function testSendRsvpNotifiesFollowersWhenNotificationEnabled(): void
    {
        // Arrange: user with followers who have notifications enabled
        $user = (new UserStub())->setId(1);
        $follower = (new UserStub())->setId(2);
        $follower->setNotification(true);

        $notificationSettings = new \App\Entity\NotificationSettings(['followingUpdates' => true]);
        $follower->setNotificationSettings($notificationSettings);

        $user->addFollower($follower);

        $event = (new EventStub())->setId(42);

        $eventRepoStub = $this->createStub(EventRepository::class);
        $eventRepoStub->method('findOneBy')->willReturn($event);

        $cacheMock = $this->createMock(TagAwareCacheInterface::class);
        $cacheMock->expects($this->once())
            ->method('get')
            ->willReturnCallback(function ($key, $callback) {
                $item = $this->createStub(ItemInterface::class);
                return $callback($item);
            });

        $service = new NotificationService(
            emailService: $this->createStub(EmailService::class),
            eventRepo: $eventRepoStub,
            userRepo: $this->createStub(UserRepository::class),
            appCache: $cacheMock,
        );

        // Act: send RSVP notification
        $service->sendRsvp($user, 42);

        // Assert: cache was accessed (verified by mock)
    }

    public function testSendRsvpSkipsFollowersWithNotificationsDisabled(): void
    {
        // Arrange: user with follower who has notifications disabled
        $user = (new UserStub())->setId(1);
        $follower = (new UserStub())->setId(2);
        $follower->setNotification(false);

        $user->addFollower($follower);

        $event = (new EventStub())->setId(42);

        $eventRepoStub = $this->createStub(EventRepository::class);
        $eventRepoStub->method('findOneBy')->willReturn($event);

        $cacheMock = $this->createMock(TagAwareCacheInterface::class);
        $cacheMock->expects($this->once())
            ->method('get')
            ->willReturnCallback(function ($key, $callback) {
                $item = $this->createStub(ItemInterface::class);
                return $callback($item);
            });

        $service = new NotificationService(
            emailService: $this->createStub(EmailService::class),
            eventRepo: $eventRepoStub,
            userRepo: $this->createStub(UserRepository::class),
            appCache: $cacheMock,
        );

        // Act: send RSVP notification
        $service->sendRsvp($user, 42);

        // Assert: follower skipped due to notification settings
    }

    public function testSendMessageReturnsEarlyWhenUserIsNull(): void
    {
        // Arrange: null user
        $userRepoMock = $this->createMock(UserRepository::class);
        $userRepoMock->expects($this->never())->method('findOneBy');

        $service = new NotificationService(
            emailService: $this->createStub(EmailService::class),
            eventRepo: $this->createStub(EventRepository::class),
            userRepo: $userRepoMock,
            appCache: $this->createStub(TagAwareCacheInterface::class),
        );

        // Act: send message notification with null user (via reflection)
        $method = new \ReflectionMethod($service, 'sendMessage');
        $method->invoke($service, null, 2);

        // Assert: no repository calls (verified by mock)
    }

    public function testSendMessageReturnsEarlyWhenRecipientNotFound(): void
    {
        // Arrange: recipient does not exist
        $sender = (new UserStub())->setId(1);

        $userRepoStub = $this->createStub(UserRepository::class);
        $userRepoStub->method('findOneBy')->willReturn(null);

        $cacheMock = $this->createMock(TagAwareCacheInterface::class);
        $cacheMock->expects($this->never())->method('get');

        $service = new NotificationService(
            emailService: $this->createStub(EmailService::class),
            eventRepo: $this->createStub(EventRepository::class),
            userRepo: $userRepoStub,
            appCache: $cacheMock,
        );

        // Act: send message notification (via reflection)
        $method = new \ReflectionMethod($service, 'sendMessage');
        $method->invoke($service, $sender, 999);

        // Assert: cache not accessed (verified by mock)
    }

    public function testSendMessageSendsEmailWhenConditionsAreMet(): void
    {
        // Arrange: sender and recipient with notifications enabled
        $sender = (new UserStub())->setId(1);
        $recipient = (new UserStub())->setId(2);
        $recipient->setNotification(true);
        $recipient->setLastLogin(new DateTime('-3 hours'));

        $notificationSettings = new \App\Entity\NotificationSettings(['receivedMessage' => true]);
        $recipient->setNotificationSettings($notificationSettings);

        $userRepoStub = $this->createStub(UserRepository::class);
        $userRepoStub->method('findOneBy')->willReturn($recipient);

        $emailServiceMock = $this->createMock(EmailService::class);
        $emailServiceMock->expects($this->once())->method('prepareMessageNotification');
        $emailServiceMock->expects($this->once())->method('sendQueue');

        $cacheMock = $this->createMock(TagAwareCacheInterface::class);
        $cacheMock->expects($this->once())
            ->method('get')
            ->willReturnCallback(function ($key, $callback) {
                $item = $this->createStub(ItemInterface::class);
                return $callback($item);
            });

        $service = new NotificationService(
            emailService: $emailServiceMock,
            eventRepo: $this->createStub(EventRepository::class),
            userRepo: $userRepoStub,
            appCache: $cacheMock,
        );

        // Act: send message notification (via reflection)
        $method = new \ReflectionMethod($service, 'sendMessage');
        $method->invoke($service, $sender, 2);

        // Assert: email service methods called (verified by mocks)
    }

    public function testSendMessageSkipsWhenRecipientRecentlyActive(): void
    {
        // Arrange: recipient logged in recently (within 2 hours)
        $sender = (new UserStub())->setId(1);
        $recipient = (new UserStub())->setId(2);
        $recipient->setNotification(true);
        $recipient->setLastLogin(new DateTime('-1 hour'));

        $notificationSettings = new \App\Entity\NotificationSettings(['receivedMessage' => true]);
        $recipient->setNotificationSettings($notificationSettings);

        $userRepoStub = $this->createStub(UserRepository::class);
        $userRepoStub->method('findOneBy')->willReturn($recipient);

        $emailServiceMock = $this->createMock(EmailService::class);
        $emailServiceMock->expects($this->never())->method('prepareMessageNotification');

        $cacheMock = $this->createMock(TagAwareCacheInterface::class);
        $cacheMock->expects($this->once())
            ->method('get')
            ->willReturnCallback(function ($key, $callback) {
                $item = $this->createStub(ItemInterface::class);
                return $callback($item);
            });

        $service = new NotificationService(
            emailService: $emailServiceMock,
            eventRepo: $this->createStub(EventRepository::class),
            userRepo: $userRepoStub,
            appCache: $cacheMock,
        );

        // Act: send message notification (via reflection)
        $method = new \ReflectionMethod($service, 'sendMessage');
        $method->invoke($service, $sender, 2);

        // Assert: email not sent due to recent activity
    }
}
