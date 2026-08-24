<?php declare(strict_types=1);

namespace Tests\Functional;

use App\Entity\Event;
use App\Enum\EventStatus;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class EventExternalRsvpTest extends WebTestCase
{
    private const string USER_EMAIL = 'Crystal.Liu@example.org';
    private const string USER_PASSWORD = '1234';
    private const int EVENT_ID = 1;

    public function testBadgeRendersOnTheEventListForAnonymousUsers(): void
    {
        // Arrange
        $client = static::createClient();
        $listed = $this->findUpcomingEvent($client);
        $this->writeExternalRsvp($client, $listed, 12);

        try {
            // Act
            $client->request('GET', '/en/events');

            // Assert
            $this->assertResponseIsSuccessful();
            $this->assertSelectorTextContains('body', '+12 external');
        } finally {
            $this->writeExternalRsvp($client, $listed, 0);
        }
    }

    public function testBadgeRendersOnTheDetailPageForAnonymousUsers(): void
    {
        // Arrange
        $client = static::createClient();
        $this->setExternalRsvp($client, 7);

        try {
            // Act
            $client->request('GET', '/en/event/' . self::EVENT_ID);

            // Assert
            $this->assertResponseIsSuccessful();
            $this->assertSelectorTextContains('body', '+7 external');
        } finally {
            $this->setExternalRsvp($client, 0);
        }
    }

    public function testBadgeRendersOnTheDetailPageForLoggedInUsers(): void
    {
        // Arrange
        $client = static::createClient();
        $this->setExternalRsvp($client, 3);
        $this->login($client);

        try {
            // Act
            $client->request('GET', '/en/event/' . self::EVENT_ID);

            // Assert
            $this->assertResponseIsSuccessful();
            $this->assertSelectorTextContains('body', '+3 external');
        } finally {
            $this->setExternalRsvp($client, 0);
        }
    }

    public function testNoBadgeRendersWhenTheCountIsZero(): void
    {
        // Arrange
        $client = static::createClient();
        $this->setExternalRsvp($client, 0);

        // Act
        $client->request('GET', '/en/event/' . self::EVENT_ID);

        // Assert
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextNotContains('body', 'external');
    }

    private function setExternalRsvp(KernelBrowser $client, int $count): void
    {
        $em = $client->getContainer()->get(EntityManagerInterface::class);
        $event = $em->getRepository(Event::class)->find(self::EVENT_ID);
        static::assertNotNull($event);
        $this->writeExternalRsvp($client, $event, $count);
    }

    private function writeExternalRsvp(KernelBrowser $client, Event $event, int $count): void
    {
        $em = $client->getContainer()->get(EntityManagerInterface::class);
        $event->setExternalRsvp($count);
        $em->flush();
    }

    private function findUpcomingEvent(KernelBrowser $client): Event
    {
        $em = $client->getContainer()->get(EntityManagerInterface::class);
        $event = $em->createQueryBuilder()
            ->select('e')
            ->from(Event::class, 'e')
            ->where('e.start > :now')
            ->andWhere('e.status = :published')
            ->andWhere('e.canceled = false')
            ->setParameter('now', new DateTimeImmutable())
            ->setParameter('published', EventStatus::Published)
            ->orderBy('e.start', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
        static::assertInstanceOf(Event::class, $event);

        return $event;
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
}
