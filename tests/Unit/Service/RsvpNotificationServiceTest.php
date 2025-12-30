<?php declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Event;
use App\Entity\NotificationSettings;
use App\Entity\User;
use App\Repository\EventRepository;
use App\Service\EmailService;
use App\Service\RsvpNotificationService;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

class RsvpNotificationServiceTest extends TestCase
{
    private EventRepository&Stub $eventRepo;
    private EmailService&MockObject $emailService;
    private TagAwareCacheInterface&Stub $appCache;
    private RsvpNotificationService $service;

    protected function setUp(): void
    {
        $this->eventRepo = $this->createStub(EventRepository::class);
        $this->emailService = $this->createMock(EmailService::class);
        $this->appCache = $this->createStub(TagAwareCacheInterface::class);
        $this->service = new RsvpNotificationService($this->eventRepo, $this->emailService, $this->appCache);
    }

    public function testNotifyFollowersForEventSendsEmail(): void
    {
        $event = $this->createStub(Event::class);
        $event->method('getId')->willReturn(1);

        $attendee = $this->createStub(User::class);
        $attendee->method('getId')->willReturn(10);
        $attendee->method('getName')->willReturn('Attendee');

        $follower = $this->createStub(User::class);
        $follower->method('getId')->willReturn(20);
        $follower->method('isNotification')->willReturn(true);
        $settings = new NotificationSettings(['followingUpdates' => true]);
        $follower->method('getNotificationSettings')->willReturn($settings);

        $event->method('getRsvp')->willReturn(new ArrayCollection([$attendee]));
        $attendee->method('getFollowers')->willReturn(new ArrayCollection([$follower]));
        $event->method('hasRsvp')->with($follower)->willReturn(false);

        $this->appCache->method('get')->willReturnCallback(function ($key, $callback) {
            if (str_starts_with($key, 'rsvp_following_notif_')) {
                // For wasNotificationSent, callback returns false (default value if not in cache)
                return $callback($this->createStub(ItemInterface::class));
            }

            return null;
        });

        $this->emailService->expects($this->once())
            ->method('prepareAggregatedRsvpNotification')
            ->with($follower, [$attendee], $event);

        $count = $this->service->notifyFollowersForEvent($event);
        $this->assertEquals(1, $count);
    }

    public function testNotifyFollowersForEventSendsEmailAggregated(): void
    {
        $event = $this->createStub(Event::class);
        $event->method('getId')->willReturn(1);

        $attendee1 = $this->createStub(User::class);
        $attendee1->method('getId')->willReturn(10);
        $attendee1->method('getName')->willReturn('Attendee 1');

        $attendee2 = $this->createStub(User::class);
        $attendee2->method('getId')->willReturn(11);
        $attendee2->method('getName')->willReturn('Attendee 2');

        $follower = $this->createStub(User::class);
        $follower->method('getId')->willReturn(20);
        $follower->method('isNotification')->willReturn(true);
        $settings = new NotificationSettings(['followingUpdates' => true]);
        $follower->method('getNotificationSettings')->willReturn($settings);

        $event->method('getRsvp')->willReturn(new ArrayCollection([$attendee1, $attendee2]));
        $attendee1->method('getFollowers')->willReturn(new ArrayCollection([$follower]));
        $attendee2->method('getFollowers')->willReturn(new ArrayCollection([$follower]));
        $event->method('hasRsvp')->with($follower)->willReturn(false);

        $this->appCache->method('get')->willReturnCallback(function ($key, $callback) {
            if (str_starts_with($key, 'rsvp_following_notif_')) {
                return $callback($this->createStub(ItemInterface::class));
            }

            return null;
        });

        $this->emailService->expects($this->once())
            ->method('prepareAggregatedRsvpNotification')
            ->with($follower, [$attendee1, $attendee2], $event);

        $count = $this->service->notifyFollowersForEvent($event);
        $this->assertEquals(1, $count);
    }

    public function testNotifyFollowersForEventSkipsIfAlreadyNotified(): void
    {
        $event = $this->createStub(Event::class);
        $event->method('getId')->willReturn(1);

        $attendee = $this->createStub(User::class);
        $attendee->method('getId')->willReturn(10);

        $follower = $this->createStub(User::class);
        $follower->method('getId')->willReturn(20);
        $follower->method('isNotification')->willReturn(true);
        $settings = new NotificationSettings(['followingUpdates' => true]);
        $follower->method('getNotificationSettings')->willReturn($settings);

        $event->method('getRsvp')->willReturn(new ArrayCollection([$attendee]));
        $attendee->method('getFollowers')->willReturn(new ArrayCollection([$follower]));
        $event->method('hasRsvp')->with($follower)->willReturn(false);

        $this->appCache->method('get')->willReturn(true);

        $this->emailService->expects($this->never())
            ->method('prepareAggregatedRsvpNotification');

        $count = $this->service->notifyFollowersForEvent($event);
        $this->assertEquals(0, $count);
    }

    public function testNotifyFollowersForEventSkipsIfNotificationsDisabled(): void
    {
        $event = $this->createStub(Event::class);
        $attendee = $this->createStub(User::class);
        $follower = $this->createStub(User::class);

        $follower->method('isNotification')->willReturn(false);

        $event->method('getRsvp')->willReturn(new ArrayCollection([$attendee]));
        $attendee->method('getFollowers')->willReturn(new ArrayCollection([$follower]));

        $this->emailService->expects($this->never())
            ->method('prepareAggregatedRsvpNotification');

        $count = $this->service->notifyFollowersForEvent($event);
        $this->assertEquals(0, $count);
    }

    public function testProcessUpcomingEvents(): void
    {
        $event1 = $this->createStub(Event::class);
        $event1->method('getRsvp')->willReturn(new ArrayCollection([]));
        $event2 = $this->createStub(Event::class);
        $event2->method('getRsvp')->willReturn(new ArrayCollection([]));

        $this->eventRepo->method('findUpcomingEventsWithinRange')->willReturn([$event1, $event2]);

        $count = $this->service->processUpcomingEvents(5);
        $this->assertEquals(0, $count);
    }

    public function testNotifyFollowersForEventEmptyAttendees(): void
    {
        $event = $this->createStub(Event::class);
        $event->method('getRsvp')->willReturn(new ArrayCollection([]));

        $count = $this->service->notifyFollowersForEvent($event);
        $this->assertEquals(0, $count);
    }

    public function testNotifyFollowersForEventSkipsIfRecipientHasRsvp(): void
    {
        $event = $this->createStub(Event::class);
        $event->method('getId')->willReturn(1);

        $attendee = $this->createStub(User::class);
        $attendee->method('getId')->willReturn(10);

        $follower = $this->createStub(User::class);
        $follower->method('getId')->willReturn(20);
        $follower->method('isNotification')->willReturn(true);
        $settings = new NotificationSettings(['followingUpdates' => true]);
        $follower->method('getNotificationSettings')->willReturn($settings);

        $event->method('getRsvp')->willReturn(new ArrayCollection([$attendee]));
        $attendee->method('getFollowers')->willReturn(new ArrayCollection([$follower]));
        $event->method('hasRsvp')->with($follower)->willReturn(true);

        $this->emailService->expects($this->never())
            ->method('prepareAggregatedRsvpNotification');

        $count = $this->service->notifyFollowersForEvent($event);
        $this->assertEquals(0, $count);
    }
}
