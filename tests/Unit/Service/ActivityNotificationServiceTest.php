<?php declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Activity\Messages\Login;
use App\Activity\Messages\RsvpYes;
use App\Activity\Messages\SendMessage;
use App\Activity\NotificationService as ActivityNotificationService;
use App\Entity\Activity;
use App\Enum\EmailType;
use App\Repository\EventRepository;
use App\Repository\UserRepository;
use App\Service\Email\EmailService;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
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
            eventRepo: $eventRepoMock,
            userRepo: $this->createStub(UserRepository::class),
            appCache: $this->createStub(TagAwareCacheInterface::class),
            emailService: $this->createStub(EmailService::class),
        );

        // Act
        $service->notify($activity);

        // Assert
    }

    public function testNotifyWithSendMessagePingsTheRecipient(): void
    {
        // Arrange
        $sender = new UserStub()->setId(1);
        $recipient = new UserStub()
            ->setId(2)
            ->setEmail('bob@example.com');

        $activity = $this->createStub(Activity::class);
        $activity->method('getUser')->willReturn($sender);
        $activity->method('getType')->willReturn(SendMessage::TYPE);
        $activity->method('getMeta')->willReturn(['user_id' => 2]);

        $userRepoMock = $this->createMock(UserRepository::class);
        $userRepoMock->expects($this->once())->method('findOneBy')->with(['id' => 2])->willReturn($recipient);

        $emailServiceMock = $this->createMock(EmailService::class);
        $emailServiceMock
            ->expects($this->once())
            ->method('dispatchPush')
            ->with(EmailType::NotificationMessage->value, 'bob@example.com', $this->isInstanceOf(DateTimeImmutable::class));
        $emailServiceMock->expects($this->never())->method('enqueue');

        $service = new ActivityNotificationService(
            eventRepo: $this->createStub(EventRepository::class),
            userRepo: $userRepoMock,
            appCache: $this->createStub(TagAwareCacheInterface::class),
            emailService: $emailServiceMock,
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
            eventRepo: $eventRepoMock,
            userRepo: $userRepoMock,
            appCache: $this->createStub(TagAwareCacheInterface::class),
            emailService: $this->createStub(EmailService::class),
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
            eventRepo: $eventRepoStub,
            userRepo: $this->createStub(UserRepository::class),
            appCache: $cacheMock,
            emailService: $this->createStub(EmailService::class),
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
            eventRepo: $eventRepoStub,
            userRepo: $this->createStub(UserRepository::class),
            appCache: $cacheMock,
            emailService: $this->createStub(EmailService::class),
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
            eventRepo: $eventRepoStub,
            userRepo: $this->createStub(UserRepository::class),
            appCache: $cacheMock,
            emailService: $this->createStub(EmailService::class),
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
            eventRepo: $this->createStub(EventRepository::class),
            userRepo: $userRepoMock,
            appCache: $this->createStub(TagAwareCacheInterface::class),
            emailService: $this->createStub(EmailService::class),
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

        $emailServiceMock = $this->createMock(EmailService::class);
        $emailServiceMock->expects($this->never())->method('dispatchPush');

        $service = new ActivityNotificationService(
            eventRepo: $this->createStub(EventRepository::class),
            userRepo: $userRepoStub,
            appCache: $this->createStub(TagAwareCacheInterface::class),
            emailService: $emailServiceMock,
        );

        // Act
        $method = new ReflectionMethod($service, 'sendMessage');
        $method->invoke($service, $sender, 999);

        // Assert
    }
}
