<?php declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Comment;
use App\Entity\EventIntervals;
use App\Entity\EventTranslation;
use App\Entity\EventTypes;
use App\Entity\Host;
use App\Entity\Image;
use App\Entity\Location;
use App\Entity\User;
use App\Tests\Unit\Entity\Stubs\EventStub;
use DateTimeImmutable;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;

class EventTest extends TestCase
{
    private EventStub $event;

    protected function setUp(): void
    {
        $this->event = new EventStub();
        $this->event->setId(1);
    }

    public function testIdGetterAndSetter(): void
    {
        $this->assertEquals(1, $this->event->getId());
    }

    public function testInitialGetterAndSetter(): void
    {
        $this->event->setInitial(true);
        $this->assertTrue($this->event->isInitial());

        $this->event->setInitial(false);
        $this->assertFalse($this->event->isInitial());
    }

    public function testStartGetterAndSetter(): void
    {
        $start = new DateTime();
        $this->event->setStart($start);

        $this->assertSame($start, $this->event->getStart());
    }

    public function testStopGetterAndSetter(): void
    {
        $stop = new DateTime();
        $this->event->setStop($stop);

        $this->assertSame($stop, $this->event->getStop());
    }

    public function testRecurringOfGetterAndSetter(): void
    {
        $recurringOf = 123;
        $this->event->setRecurringOf($recurringOf);

        $this->assertEquals($recurringOf, $this->event->getRecurringOf());
    }

    public function testRecurringRuleGetterAndSetter(): void
    {
        $recurringRule = EventIntervals::Weekly;
        $this->event->setRecurringRule($recurringRule);

        $this->assertSame($recurringRule, $this->event->getRecurringRule());
    }

    public function testUserGetterAndSetter(): void
    {
        $user = new User();
        $this->event->setUser($user);

        $this->assertSame($user, $this->event->getUser());
    }

    public function testLocationGetterAndSetter(): void
    {
        $location = new Location();
        $this->event->setLocation($location);

        $this->assertSame($location, $this->event->getLocation());
    }

    public function testCreatedAtGetterAndSetter(): void
    {
        $date = new DateTimeImmutable();
        $this->event->setCreatedAt($date);

        $this->assertSame($date, $this->event->getCreatedAt());
    }

    public function testHostGetterSetterAndCollectionMethods(): void
    {
        // Test initial empty collection
        $this->assertInstanceOf(ArrayCollection::class, $this->event->getHost());
        $this->assertCount(0, $this->event->getHost());

        // Test adding a host
        $host = new Host();
        $this->event->addHost($host);
        $this->assertCount(1, $this->event->getHost());
        $this->assertTrue($this->event->getHost()->contains($host));

        // Test adding the same host doesn't duplicate
        $this->event->addHost($host);
        $this->assertCount(1, $this->event->getHost());

        // Test removing a host
        $this->event->removeHost($host);
        $this->assertCount(0, $this->event->getHost());
        $this->assertFalse($this->event->getHost()->contains($host));

        // Test setting the entire collection
        $hosts = new ArrayCollection([$host]);
        $this->event->setHost($hosts);
        $this->assertSame($hosts, $this->event->getHost());
    }

    public function testRsvpGetterAndCollectionMethods(): void
    {
        // Test initial empty collection
        $this->assertInstanceOf(ArrayCollection::class, $this->event->getRsvp());
        $this->assertCount(0, $this->event->getRsvp());

        // Test adding an RSVP
        $user = new User();
        $this->event->addRsvp($user);
        $this->assertCount(1, $this->event->getRsvp());
        $this->assertTrue($this->event->getRsvp()->contains($user));
        $this->assertTrue($this->event->hasRsvp($user));

        // Test adding the same user doesn't duplicate
        $this->event->addRsvp($user);
        $this->assertCount(1, $this->event->getRsvp());

        // Test removing an RSVP
        $this->event->removeRsvp($user);
        $this->assertCount(0, $this->event->getRsvp());
        $this->assertFalse($this->event->getRsvp()->contains($user));
        $this->assertFalse($this->event->hasRsvp($user));

        // Test toggle RSVP (add)
        $this->event->toggleRsvp($user);
        $this->assertTrue($this->event->hasRsvp($user));

        // Test toggle RSVP (remove)
        $this->event->toggleRsvp($user);
        $this->assertFalse($this->event->hasRsvp($user));
    }

    public function testPreviewImageGetterAndSetter(): void
    {
        $image = new Image();
        $this->event->setPreviewImage($image);

        $this->assertSame($image, $this->event->getPreviewImage());
    }

    public function testTypeGetterAndSetter(): void
    {
        $type = EventTypes::Regular;
        $this->event->setType($type);

        $this->assertSame($type, $this->event->getType());
    }

    public function testHasMapWithNoLocation(): void
    {
        $this->event->setLocation(null);
        $this->assertFalse($this->event->hasMap());
    }

    public function testHasMapWithLocationNoCoordinates(): void
    {
        $location = new Location();
        $this->event->setLocation($location);
        $this->assertFalse($this->event->hasMap());
    }

    public function testHasMapWithLocationAndCoordinates(): void
    {
        $location = new Location();
        $location->setLatitude('10.0');
        $location->setLongitude('20.0');

        $this->event->setLocation($location);
        $this->assertTrue($this->event->hasMap());
    }

    public function testCommentsGetterAndCollectionMethods(): void
    {
        // Test initial empty collection
        $this->assertInstanceOf(ArrayCollection::class, $this->event->getComments());
        $this->assertCount(0, $this->event->getComments());

        // Test adding a comment
        $comment = new Comment();
        $this->event->addComment($comment);
        $this->assertCount(1, $this->event->getComments());
        $this->assertTrue($this->event->getComments()->contains($comment));
        $this->assertSame($this->event, $comment->getEvent());

        // Test adding the same comment doesn't duplicate
        $this->event->addComment($comment);
        $this->assertCount(1, $this->event->getComments());

        // Test removing a comment
        $this->event->removeComment($comment);
        $this->assertCount(0, $this->event->getComments());
        $this->assertFalse($this->event->getComments()->contains($comment));
        $this->assertNull($comment->getEvent());
    }

    public function testTranslationsGetterSetterAndCollectionMethods(): void
    {
        // Test initial empty collection
        $this->assertInstanceOf(ArrayCollection::class, $this->event->getTranslation());
        $this->assertCount(0, $this->event->getTranslation());

        // Test adding a translation
        $translation = new EventTranslation();
        $this->event->addTranslation($translation);
        $this->assertCount(1, $this->event->getTranslation());
        $this->assertTrue($this->event->getTranslation()->contains($translation));
        $this->assertSame($this->event, $translation->getEvent());

        // Test adding the same translation doesn't duplicate
        $this->event->addTranslation($translation);
        $this->assertCount(1, $this->event->getTranslation());

        // Test removing a comment
        $this->event->removeTranslation($translation);
        $this->assertCount(0, $this->event->getTranslation());
        $this->assertFalse($this->event->getComments()->contains($translation));
        $this->assertNull($translation->getEvent());

    }

    public function testGetTitleAndDescription(): void
    {
        // Test with no translations
        $this->assertEquals('', $this->event->getTitle('en'));
        $this->assertEquals('', $this->event->getDescription('en'));

        // Test with translations
        $translation = new EventTranslation();
        $translation->setLanguage('en');
        $translation->setTitle('Test Event');
        $translation->setDescription('Test Description');
        $this->event->addTranslation($translation);

        $this->assertEquals('Test Event', $this->event->getTitle('en'));
        $this->assertEquals('Test Description', $this->event->getDescription('en'));

        // Test with non-existent language
        $this->assertEquals('', $this->event->getTitle('fr'));
        $this->assertEquals('', $this->event->getDescription('fr'));
    }
}
