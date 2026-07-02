<?php declare(strict_types=1);

namespace Tests\Functional\Controller\Admin;

use App\DataFixtures\EventFixture;
use App\Entity\Event;
use App\Entity\EventTranslation;
use App\Entity\User;
use App\Service\Event\RecurringEventService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

class EventRescheduleFlowTest extends WebTestCase
{
    private const string ADMIN_EMAIL = 'Admin@example.org';
    private const string ADMIN_PASSWORD = '1234';

    public function testScheduleChangeWithAllFollowingRendersConfirmationWithoutSaving(): void
    {
        // Arrange
        $client = static::createClient();
        $this->loginAsAdmin($client);
        [$parentId, $childId, $oldStart] = $this->prepareSeriesWithRsvpdChild($client);
        $newStart = new DateTime('+10 days')->setTime(20, 15);

        // Act
        $crawler = $this->submitScheduleChange($client, $parentId, $newStart);

        // Assert: confirmation page renders instead of saving
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('article.message.is-warning');
        $this->assertSelectorTextContains('button.is-danger', 'Reschedule series');
        $this->assertSelectorExists('table.is-fullwidth span.tag.is-warning');
        static::assertGreaterThan(0, $crawler->filter('input[name="reschedule_confirm"]')->count());

        // Assert: nothing flushed
        $em = $this->getEntityManager($client);
        $em->clear();
        $parent = $em->getRepository(Event::class)->find($parentId);
        static::assertSame($oldStart, $parent->getStart()->format('Y-m-d H:i'));
        $child = $em->getRepository(Event::class)->find($childId);
        static::assertCount(1, $child->getRsvp());
    }

    public function testConfirmMovesChildrenAndRemovesRsvps(): void
    {
        // Arrange
        $client = static::createClient();
        $this->loginAsAdmin($client);
        [$parentId, $childId] = $this->prepareSeriesWithRsvpdChild($client);
        $newStart = new DateTime('+10 days')->setTime(20, 15);
        $crawler = $this->submitScheduleChange($client, $parentId, $newStart);

        // Act
        $confirmForm = $crawler->selectButton('Reschedule series')->form();
        $client->submit($confirmForm);

        // Assert: PRG back to edit
        $this->assertResponseRedirects('/en/admin/events/' . $parentId . '/edit');
        $client->followRedirect();
        $this->assertResponseIsSuccessful();

        $em = $this->getEntityManager($client);
        $em->clear();
        $parent = $em->getRepository(Event::class)->find($parentId);
        static::assertSame($newStart->format('Y-m-d H:i'), $parent->getStart()->format('Y-m-d H:i'));
        static::assertGreaterThan(0, $parent->getRsvp()->count(), 'the edited anchor keeps its RSVPs');

        // The first future child maps onto the first occurrence after the anchor
        $child = $em->getRepository(Event::class)->find($childId);
        $expectedChildStart = (clone $newStart)->modify('+7 days');
        static::assertSame($expectedChildStart->format('Y-m-d H:i'), $child->getStart()->format('Y-m-d H:i'));
        static::assertCount(0, $child->getRsvp(), 'moved child loses its RSVPs');
    }

    public function testCancelReturnsToEditFormWithTypedValuesAndNoSave(): void
    {
        // Arrange
        $client = static::createClient();
        $this->loginAsAdmin($client);
        [$parentId, $childId, $oldStart] = $this->prepareSeriesWithRsvpdChild($client);
        $newStart = new DateTime('+10 days')->setTime(20, 15);
        $crawler = $this->submitScheduleChange($client, $parentId, $newStart);

        // Act
        $cancelForm = $crawler->selectButton('Keep editing')->form();
        $crawler = $client->submit($cancelForm);

        // Assert: edit form renders with the submitted value still in the field
        $this->assertResponseIsSuccessful();
        static::assertSame($newStart->format('Y-m-d\TH:i'), $crawler->filter('input[name="event[start]"]')->attr('value'));

        // Assert: nothing flushed
        $em = $this->getEntityManager($client);
        $em->clear();
        $parent = $em->getRepository(Event::class)->find($parentId);
        static::assertSame($oldStart, $parent->getStart()->format('Y-m-d H:i'));
        $child = $em->getRepository(Event::class)->find($childId);
        static::assertCount(1, $child->getRsvp());
    }

    private function submitScheduleChange(KernelBrowser $client, int $parentId, DateTime $newStart): Crawler
    {
        $crawler = $client->request('GET', '/en/admin/events/' . $parentId . '/edit');
        $this->assertResponseIsSuccessful();

        $form = $crawler->selectButton('Save')->form();
        $form['event[start]'] = $newStart->format('Y-m-d\TH:i');
        $form['event[stop]'] = (clone $newStart)->modify('+3 hours')->format('Y-m-d\TH:i');
        $form['event[allFollowing]']->tick();

        return $client->submit($form);
    }

    /**
     * @return array{int, int, string} parent id, id of the first future child (carrying one RSVP), old parent start
     */
    private function prepareSeriesWithRsvpdChild(KernelBrowser $client): array
    {
        $container = $client->getContainer();
        $container->get(RecurringEventService::class)->extentRecurringEvents();

        $em = $this->getEntityManager($client);
        $em->clear();

        $parent = $this->getEventByTitle($client, EventFixture::WEEKLY_GO_STUDY);
        $children = $em->getRepository(Event::class)->findBy(['recurringOf' => $parent->getId()], ['start' => 'ASC']);
        $futureChild = null;
        foreach ($children as $child) {
            if ($child->getStart() <= new DateTime()) {
                continue;
            }

            $futureChild = $child;
            break;
        }
        static::assertNotNull($futureChild, 'series should have at least one future auto child');

        $attendee = $parent->getRsvp()->first();
        static::assertInstanceOf(User::class, $attendee);
        $futureChild->addRsvp($attendee);
        $em->flush();

        return [$parent->getId(), $futureChild->getId(), $parent->getStart()->format('Y-m-d H:i')];
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

    private function getEventByTitle(KernelBrowser $client, string $title): Event
    {
        $em = $this->getEntityManager($client);
        $translation = $em->getRepository(EventTranslation::class)->findOneBy(['title' => $title]);
        static::assertNotNull($translation, "Event titled {$title} should exist in fixtures");
        $event = $translation->getEvent();
        static::assertInstanceOf(Event::class, $event);

        return $event;
    }
}
