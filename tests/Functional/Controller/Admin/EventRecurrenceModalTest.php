<?php declare(strict_types=1);

namespace Tests\Functional\Controller\Admin;

use App\Entity\Event;
use App\Enum\EventInterval;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

class EventRecurrenceModalTest extends WebTestCase
{
    private const string ADMIN_EMAIL = 'Admin@example.org';
    private const string ADMIN_PASSWORD = '1234';

    public function testTheBuilderOpensOnDefaultsRegardlessOfTheSeriesRule(): void
    {
        // Arrange
        $client = static::createClient();
        $this->loginAsAdmin($client);
        $eventId = $this->findEventWithRule($client, EventInterval::BiMonthly);

        // Act
        $crawler = $client->request('GET', '/en/admin/events/' . $eventId . '/edit');

        // Assert
        $this->assertResponseIsSuccessful();
        static::assertSame('month', $this->selectedOption($crawler, '#recurrence-period'));
        static::assertSame('weekday', $crawler->filter('input[name="recurrence-mode"][checked]')->attr('value'));
        static::assertStringNotContainsString('is-hidden', (string) $crawler->filter('#recurrence-ordinal-wrap')->attr('class'));
    }

    public function testTheRuleInForceIsShownAsTextNotPreFilled(): void
    {
        // Arrange
        $client = static::createClient();
        $this->loginAsAdmin($client);
        $eventId = $this->findEventWithRule($client, EventInterval::BiMonthly);

        // Act
        $crawler = $client->request('GET', '/en/admin/events/' . $eventId . '/edit');

        // Assert
        $this->assertResponseIsSuccessful();
        static::assertStringContainsString(
            'Current rule:',
            $crawler->filter('#recurrence-modal .modal-card-body')->text(),
        );
    }

    public function testANewEventCarriesNoCurrentRuleLine(): void
    {
        // Arrange
        $client = static::createClient();
        $this->loginAsAdmin($client);

        // Act
        $crawler = $client->request('GET', '/en/admin/events/new');

        // Assert
        $this->assertResponseIsSuccessful();
        static::assertStringNotContainsString(
            'Current rule:',
            $crawler->filter('#recurrence-modal .modal-card-body')->text(),
        );
        static::assertSame('month', $this->selectedOption($crawler, '#recurrence-period'));
    }

    private function selectedOption(Crawler $crawler, string $selector): ?string
    {
        $selected = $crawler->filter($selector . ' option[selected]');

        return $selected->count() > 0 ? $selected->attr('value') : null;
    }

    private function findEventWithRule(KernelBrowser $client, EventInterval $rule): int
    {
        $em = $client->getContainer()->get('doctrine')->getManager();
        assert($em instanceof EntityManagerInterface);

        $event = $em->createQueryBuilder()
            ->select('e')
            ->from(Event::class, 'e')
            ->join('e.series', 's')
            ->where('s.rule = :rule')
            ->setParameter('rule', $rule->value)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        static::assertInstanceOf(Event::class, $event, 'fixtures carry no series with rule ' . $rule->name);

        return (int) $event->getId();
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
