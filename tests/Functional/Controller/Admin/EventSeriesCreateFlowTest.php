<?php declare(strict_types=1);

namespace Tests\Functional\Controller\Admin;

use App\Entity\Event;
use App\Entity\EventSeries;
use App\Enum\EventInterval;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

class EventSeriesCreateFlowTest extends WebTestCase
{
    private const string ADMIN_EMAIL = 'Admin@example.org';
    private const string ADMIN_PASSWORD = '1234';

    public function testCreateWithRuleAndNamePersistsSeries(): void
    {
        // Arrange
        $client = static::createClient();
        $this->loginAsAdmin($client);
        $start = new DateTime('2031-03-03 13:37');

        // Act
        $this->submitNewEventForm($client, $start, seriesRule: (string) EventInterval::Weekly->value, seriesName: 'My Test Series');

        // Assert: PRG to the edit page, series row created and attached
        $this->assertResponseRedirects();

        $em = $this->getEntityManager($client);
        $em->clear();
        $series = $em->getRepository(EventSeries::class)->findOneBy(['name' => 'My Test Series']);
        static::assertNotNull($series);
        static::assertSame(EventInterval::Weekly, $series->getRule());

        $event = $em->getRepository(Event::class)->findOneBy(['start' => $start]);
        static::assertNotNull($event);
        static::assertSame($series->getId(), $event->getSeries()?->getId());
    }

    public function testCreateWithRuleAndBlankNameRendersErrorAndPersistsNothing(): void
    {
        // Arrange
        $client = static::createClient();
        $this->loginAsAdmin($client);
        $em = $this->getEntityManager($client);
        $eventCountBefore = count($em->getRepository(Event::class)->findAll());
        $seriesCountBefore = count($em->getRepository(EventSeries::class)->findAll());
        $start = new DateTime('2031-03-03 13:37');

        // Act
        $this->submitNewEventForm($client, $start, seriesRule: (string) EventInterval::Weekly->value, seriesName: '');

        // Assert: form re-renders with the validator message, nothing persisted
        $this->assertResponseIsUnprocessable();
        static::assertStringContainsString('Please enter a name for the series.', (string) $client->getResponse()->getContent());

        $em->clear();
        static::assertCount($eventCountBefore, $em->getRepository(Event::class)->findAll());
        static::assertCount($seriesCountBefore, $em->getRepository(EventSeries::class)->findAll());
    }

    public function testCreateWithoutRulePersistsOneTimeEvent(): void
    {
        // Arrange
        $client = static::createClient();
        $this->loginAsAdmin($client);
        $em = $this->getEntityManager($client);
        $seriesCountBefore = count($em->getRepository(EventSeries::class)->findAll());
        $start = new DateTime('2031-03-03 13:37');

        // Act
        $this->submitNewEventForm($client, $start, seriesRule: '', seriesName: '');

        // Assert
        $this->assertResponseRedirects();

        $em->clear();
        $event = $em->getRepository(Event::class)->findOneBy(['start' => $start]);
        static::assertNotNull($event);
        static::assertNull($event->getSeries());
        static::assertCount($seriesCountBefore, $em->getRepository(EventSeries::class)->findAll());
    }

    private function submitNewEventForm(KernelBrowser $client, DateTime $start, string $seriesRule, string $seriesName): Crawler
    {
        $crawler = $client->request('GET', '/en/admin/events/new');
        $this->assertResponseIsSuccessful();

        $form = $crawler->selectButton('Add')->form();
        $form['event[start]'] = $start->format('Y-m-d\TH:i');
        $form['event[stop]'] = (clone $start)->modify('+3 hours')->format('Y-m-d\TH:i');
        $form['event[location]'] = $crawler->filter('#event_location option')->first()->attr('value');
        $form['event[seriesRule]'] = $seriesRule;
        $form['event[seriesName]'] = $seriesName;

        return $client->submit($form);
    }

    private function getEntityManager(KernelBrowser $client): EntityManagerInterface
    {
        return $client->getContainer()->get(EntityManagerInterface::class);
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
}
