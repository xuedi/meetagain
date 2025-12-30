<?php declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Event;
use App\Entity\NotificationSettings;
use App\Entity\User;
use App\Repository\EventRepository;
use App\Service\EmailService;
use App\Service\RsvpNotificationService;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

class RsvpNotificationServiceTest extends TestCase
{
    private EventRepository $eventRepo;
    private EmailService $emailService;
    private TagAwareCacheInterface $appCache;
    private RsvpNotificationService $service;

    protected function setUp(): void
    {
        $this->eventRepo = $this->createMock(EventRepository::class);
        $this->emailService = $this->createMock(EmailService::class);
        $this->appCache = $this->createMock(TagAwareCacheInterface::class);
        $this->service = new RsvpNotificationService($this->eventRepo, $this->emailService, $this->appCache);
    }

    public function testNotifyFollowersForEventSendsEmail(): void
    {
        $event = $this->createMock(Event::class);
        $event->method('getId')->willReturn(1);

        $attendee = $this->createMock(User::class);
        $attendee->method('getId')->willReturn(10);
        $attendee->method('getName')->willReturn('Attendee');

        $follower = $this->createMock(User::class);
        $follower->method('getId')->willReturn(20);
        $follower->method('isNotification')->willReturn(true);
        $settings = new NotificationSettings(['followingUpdates' => true]);
        $follower->method('getNotificationSettings')->willReturn($settings);

        $event->method('getRsvp')->willReturn(new ArrayCollection([$attendee]));
        $attendee->method('getFollowers')->willReturn(new ArrayCollection([$follower]));
        $event->method('hasRsvp')->with($follower)->willReturn(false);

        $this->appCache->method('get')->willReturnCallback(function($key, $callback) {
            if (str_starts_with($key, 'rsvp_following_notif_')) {
                // For wasNotificationSent, callback returns false (default value if not in cache)
                return $callback($this->createMock(ItemInterface::class));
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
        $event = $this->createMock(Event::class);
        $event->method('getId')->willReturn(1);

        $attendee1 = $this->createMock(User::class);
        $attendee1->method('getId')->willReturn(10);
        $attendee1->method('getName')->willReturn('Attendee 1');

        $attendee2 = $this->createMock(User::class);
        $attendee2->method('getId')->willReturn(11);
        $attendee2->method('getName')->willReturn('Attendee 2');

        $follower = $this->createMock(User::class);
        $follower->method('getId')->willReturn(20);
        $follower->method('isNotification')->willReturn(true);
        $settings = new NotificationSettings(['followingUpdates' => true]);
        $follower->method('getNotificationSettings')->willReturn($settings);

        $event->method('getRsvp')->willReturn(new ArrayCollection([$attendee1, $attendee2]));
        $attendee1->method('getFollowers')->willReturn(new ArrayCollection([$follower]));
        $attendee2->method('getFollowers')->willReturn(new ArrayCollection([$follower]));
        $event->method('hasRsvp')->with($follower)->willReturn(false);

        $this->appCache->method('get')->willReturnCallback(function($key, $callback) {
            if (str_starts_with($key, 'rsvp_following_notif_')) {
                return $callback($this->createMock(ItemInterface::class));
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
        $event = $this->createMock(Event::class);
        $event->method('getId')->willReturn(1);

        $attendee = $this->createMock(User::class);
        $attendee->method('getId')->willReturn(10);

        $follower = $this->createMock(User::class);
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
        $event = $this->createMock(Event::class);
        $attendee = $this->createMock(User::class);
        $follower = $this->createMock(User::class);
        
        $follower->method('isNotification')->willReturn(false);

        $event->method('getRsvp')->willReturn(new ArrayCollection([$attendee]));
        $attendee->method('getFollowers')->willReturn(new ArrayCollection([$follower]));

        $this->emailService->expects($this->never())
            ->method('prepareAggregatedRsvpNotification');

        $count = $this->service->notifyFollowersForEvent($event);
        $this->assertEquals(0, $count);
    }
}
