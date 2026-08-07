<?php declare(strict_types=1);

namespace Tests\Functional\Controller;

use App\Entity\Event;
use App\Entity\RsvpGuest;
use App\Entity\User;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class EventRsvpGuestsTest extends WebTestCase
{
    private const string USER_EMAIL = 'Crystal.Liu@example.org';
    private const string USER_PASSWORD = '1234';
    private const int EVENT_ID = 1;

    public function testGuestChangeRequiresLogin(): void
    {
        // Arrange
        $client = static::createClient();

        // Act
        $client->request('POST', '/en/event/rsvpGuests/1/add/', ['_token' => '']);

        // Assert
        $this->assertResponseRedirects();
    }

    public function testGuestChangeRejectsInvalidCsrfToken(): void
    {
        // Arrange
        $client = static::createClient();
        $this->login($client);

        // Act
        $client->request('POST', '/en/event/rsvpGuests/1/add/', ['_token' => 'invalid']);

        // Assert
        $this->assertResponseStatusCodeSame(400);
    }

    public function testAddGuestWithoutRsvpKeepsDatabaseEmpty(): void
    {
        // Arrange
        $client = static::createClient();
        $this->login($client);
        $this->setRsvp($client, false);
        $token = $this->seedCsrfToken($client, 'app_event_rsvp_guests');

        // Act
        $client->request('POST', '/en/event/rsvpGuests/1/add/', ['_token' => $token]);

        // Assert
        $this->assertResponseRedirects();
        static::assertNull($this->findGuestRow($client));
    }

    public function testAddGuestIncrementsUpToTheCap(): void
    {
        // Arrange
        $client = static::createClient();
        $this->login($client);
        $this->setRsvp($client, true);
        $token = $this->seedCsrfToken($client, 'app_event_rsvp_guests');

        // Act
        for ($i = 0; $i < RsvpGuest::MAX_GUESTS + 2; $i++) {
            $client->request('POST', '/en/event/rsvpGuests/1/add/', ['_token' => $token]);
        }

        // Assert
        static::assertSame(RsvpGuest::MAX_GUESTS, $this->findGuestRow($client)?->getGuests());
    }

    public function testRemoveGuestDecrementsAndDeletesRowAtZero(): void
    {
        // Arrange
        $client = static::createClient();
        $this->login($client);
        $this->setRsvp($client, true);
        $token = $this->seedCsrfToken($client, 'app_event_rsvp_guests');
        $client->request('POST', '/en/event/rsvpGuests/1/add/', ['_token' => $token]);
        $client->request('POST', '/en/event/rsvpGuests/1/add/', ['_token' => $token]);

        // Act
        $client->request('POST', '/en/event/rsvpGuests/1/remove/', ['_token' => $token]);

        // Assert
        static::assertSame(1, $this->findGuestRow($client)?->getGuests());

        // Act
        $client->request('POST', '/en/event/rsvpGuests/1/remove/', ['_token' => $token]);

        // Assert
        static::assertNull($this->findGuestRow($client));
    }

    public function testUnRsvpDeletesGuestRow(): void
    {
        // Arrange
        $client = static::createClient();
        $this->login($client);
        $this->setRsvp($client, true);
        $guestToken = $this->seedCsrfToken($client, 'app_event_rsvp_guests');
        $client->request('POST', '/en/event/rsvpGuests/1/add/', ['_token' => $guestToken]);
        static::assertNotNull($this->findGuestRow($client));

        // Act
        $toggleToken = $this->seedCsrfToken($client, 'app_event_toggle_rsvp');
        $client->request('POST', '/en/event/toggleRsvp/1/', ['_token' => $toggleToken]);

        // Assert
        static::assertNull($this->findGuestRow($client));
    }

    public function testBadgeRendersOnOwnTileAfterAddingGuest(): void
    {
        // Arrange
        $client = static::createClient();
        $this->login($client);
        $this->setRsvp($client, true);
        $token = $this->seedCsrfToken($client, 'app_event_rsvp_guests');
        $client->request('POST', '/en/event/rsvpGuests/1/add/', ['_token' => $token]);

        // Act
        $client->request('GET', '/en/event/1');

        // Assert
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('.rsvp-guest-count');
        $this->assertSelectorTextContains('.rsvp-guest-count', '+1');
        $this->assertSelectorExists('[data-rsvp-guests="add"]');
        $this->assertSelectorExists('[data-rsvp-guests="remove"]');
    }

    public function testGuestChangeReturnsJsonForXmlHttpRequest(): void
    {
        // Arrange
        $client = static::createClient();
        $this->login($client);
        $this->setRsvp($client, true);
        $token = $this->seedCsrfToken($client, 'app_event_rsvp_guests');

        // Act
        $client->request('POST', '/en/event/rsvpGuests/1/add/', ['_token' => $token], [], ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);

        // Assert
        $this->assertResponseIsSuccessful();
        $payload = json_decode((string) $client->getResponse()->getContent(), true);
        static::assertSame(1, $payload['count']);
        static::assertFalse($payload['capped']);
    }

    private function login(KernelBrowser $client): void
    {
        $crawler = $client->request('GET', '/en/login');
        $form = $crawler
            ->selectButton('Login')
            ->form([
                '_username' => self::USER_EMAIL,
                '_password' => self::USER_PASSWORD,
            ]);
        $client->submit($form);
        $client->followRedirect();
    }

    private function setRsvp(KernelBrowser $client, bool $attending): void
    {
        $em = $client->getContainer()->get(EntityManagerInterface::class);
        $event = $em->find(Event::class, self::EVENT_ID);
        $event->setStart(new DateTime('+5 days'));
        $user = $em->getRepository(User::class)->findOneBy(['email' => self::USER_EMAIL]);
        if ($attending) {
            $event->addRsvp($user);
        } else {
            $event->removeRsvp($user);
        }
        $em->flush();
    }

    private function seedCsrfToken(KernelBrowser $client, string $tokenId): string
    {
        $token = 'test-csrf-' . $tokenId;
        $session = $client->getSession();
        $session->set('_csrf/' . $tokenId . self::EVENT_ID, $token);
        $session->save();

        return $token;
    }

    private function findGuestRow(KernelBrowser $client): ?RsvpGuest
    {
        $em = $client->getContainer()->get(EntityManagerInterface::class);
        $em->clear();

        return $em->getRepository(RsvpGuest::class)->findOneBy([
            'event' => self::EVENT_ID,
            'user' => $em->getRepository(User::class)->findOneBy(['email' => self::USER_EMAIL]),
        ]);
    }
}
