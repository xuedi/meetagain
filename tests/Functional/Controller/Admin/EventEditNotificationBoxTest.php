<?php declare(strict_types=1);

namespace Tests\Functional\Controller\Admin;

use App\DataFixtures\EventFixture;
use App\Entity\Event;
use App\Entity\EventTranslation;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class EventEditNotificationBoxTest extends WebTestCase
{
    private const string ADMIN_EMAIL = 'Admin@example.org';
    private const string ADMIN_PASSWORD = '1234';

    public function testNotificationBoxAndCheckboxRenderForEventWithOptedInRsvps(): void
    {
        // Arrange
        $client = static::createClient();
        $this->loginAsAdmin($client);
        $event = $this->getEventByTitle($client, EventFixture::BERLIN_TOURNAMENT);

        // Act
        $crawler = $client->request('GET', '/en/admin/events/' . $event->getId() . '/edit');

        // Assert: Berlin tournament has admin as creator + 3 non-creator RSVPs (ADEM, CRYSTAL, ALISA)
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('div.notification.is-info', '3');
        $this->assertSelectorTextContains('div.notification.is-info', 'decided to attend');
        static::assertGreaterThan(
            0,
            $crawler->filter('input[name="event[notifyAttendees]"]')->count(),
            'Notify-attendees checkbox should be rendered when there are opted-in RSVPs',
        );
    }

    public function testNotificationBoxHiddenWhenAllRsvpsOptedOut(): void
    {
        // Arrange
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        // Flip every non-creator RSVP's attendedEventUpdate toggle off
        $event = $this->getEventByTitle($client, EventFixture::BERLIN_TOURNAMENT);
        $creatorId = $event->getUser()?->getId();
        $changed = [];
        foreach ($event->getRsvp() as $rsvp) {
            if (!$rsvp instanceof User) {
                continue;
            }
            if ($rsvp->getId() === $creatorId) {
                continue;
            }
            $settings = $rsvp->getNotificationSettings();
            $settings->attendedEventUpdate = false;
            $rsvp->setNotificationSettings($settings);
            $changed[] = $rsvp;
        }
        $em->flush();

        try {
            $this->loginAsAdmin($client);

            // Act
            $crawler = $client->request('GET', '/en/admin/events/' . $event->getId() . '/edit');

            // Assert
            $this->assertResponseIsSuccessful();
            static::assertSelectorNotExists('div.notification.is-info');
            static::assertSame(
                0,
                $crawler->filter('input[name="event[notifyAttendees]"]')->count(),
                'Notify-attendees checkbox should be hidden when no opted-in RSVPs',
            );
        } finally {
            // Reset
            foreach ($changed as $rsvp) {
                $settings = $rsvp->getNotificationSettings();
                $settings->attendedEventUpdate = true;
                $rsvp->setNotificationSettings($settings);
            }
            $em->flush();
        }
    }

    private function loginAsAdmin(KernelBrowser $client): void
    {
        $crawler = $client->request('GET', '/en/login');
        $form = $crawler
            ->selectButton('Login')
            ->form([
                '_username' => self::ADMIN_EMAIL,
                '_password' => self::ADMIN_PASSWORD,
            ]);
        $client->submit($form);
        $client->followRedirect();
    }

    private function getEventByTitle(KernelBrowser $client, string $title): Event
    {
        $em = $client->getContainer()->get(EntityManagerInterface::class);
        $translation = $em->getRepository(EventTranslation::class)->findOneBy(['title' => $title]);
        static::assertNotNull($translation, "Event titled {$title} should exist in fixtures");
        $event = $translation->getEvent();
        static::assertInstanceOf(Event::class, $event);

        return $event;
    }
}
