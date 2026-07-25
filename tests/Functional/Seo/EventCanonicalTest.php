<?php declare(strict_types=1);

namespace Tests\Functional\Seo;

use App\DataFixtures\EventFixture;
use App\Entity\Event;
use App\Entity\EventCanonicalRoot;
use App\Entity\EventTranslation;
use App\Enum\EventCanonicalRootType;
use DateTime;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Existing fixture events are reused as series members rather than freshly created ones:
 * only events that already carry their plugin-side ownership mapping are reachable on the
 * public event page. These cases use the Go-club events, which share one single-language owner.
 *
 * Hreflang needs an owner publishing in two languages and no core fixture has one, so those cases
 * cannot live here - they belong to whichever plugin ships such a fixture.
 */
class EventCanonicalTest extends WebTestCase
{
    private const string SERIES_ROOT_TITLE = EventFixture::WEEKLY_GO_STUDY;
    private const string BRANCH_TITLE = EventFixture::ONLINE_SIMULTANEOUS;
    private const string FOLLOWER_TITLE = EventFixture::WEEKEND_RETREAT;
    private const string ONE_OFF_TITLE = EventFixture::BERLIN_TOURNAMENT;

    private function getEventByTitle(KernelBrowser $client, string $title): Event
    {
        $em = $client->getContainer()->get(EntityManagerInterface::class);
        $translation = $em->getRepository(EventTranslation::class)->findOneBy(['title' => $title]);
        static::assertInstanceOf(EventTranslation::class, $translation);
        $event = $translation->getEvent();
        static::assertInstanceOf(Event::class, $event);

        return $event;
    }

    /**
     * @param array<string, string> $descriptionByLocale locale => description; locales left out clone the root
     */
    private function adoptIntoSeries(KernelBrowser $client, string $title, Event $root, string $modifier, array $descriptionByLocale = []): Event
    {
        $em = $client->getContainer()->get(EntityManagerInterface::class);
        $member = $this->getEventByTitle($client, $title);

        $member->setSeries($root->getSeries());
        $member->setStart(new DateTime($root->getStart()->format('Y-m-d H:i:s'))->modify($modifier));

        foreach ($root->getTranslation() as $rootTranslation) {
            $locale = (string) $rootTranslation->getLanguage();
            $translation = $member->findTranslation($locale);
            if ($translation === null) {
                $translation = new EventTranslation();
                $translation->setEvent($member);
                $translation->setLanguage($locale);
                $member->addTranslation($translation);
            }
            $translation->setTitle((string) $rootTranslation->getTitle());
            $translation->setTeaser((string) $rootTranslation->getTeaser());
            $translation->setDescription($descriptionByLocale[$locale] ?? (string) $rootTranslation->getDescription());
            $em->persist($translation);
        }

        $em->persist($member);
        $em->flush();

        return $member;
    }

    private function markMember(KernelBrowser $client, Event $event, string $locale, EventCanonicalRootType $type): void
    {
        $em = $client->getContainer()->get(EntityManagerInterface::class);
        $marker = (new EventCanonicalRoot())
            ->setEvent($event)
            ->setLocale($locale)
            ->setType($type)
            ->setCreatedAt(new DateTimeImmutable());
        $em->persist($marker);
        $em->flush();
    }

    public function testFollowerPageCanonicalizesToTheSeriesRootWithoutNoindex(): void
    {
        // Arrange
        $client = static::createClient();
        $root = $this->getEventByTitle($client, self::SERIES_ROOT_TITLE);
        $follower = $this->adoptIntoSeries($client, self::FOLLOWER_TITLE, $root, '+7 days');

        // Act
        $crawler = $client->request('GET', '/en/event/' . $follower->getId());

        // Assert
        $this->assertResponseIsSuccessful();
        static::assertStringEndsWith(
            '/en/event/' . $root->getId(),
            (string) $crawler->filter('link[rel="canonical"]')->attr('href'),
        );
        static::assertSame(0, $crawler->filter('meta[name="robots"]')->count());
    }

    public function testCanonicalLinkHeaderJsonLdAndOgUrlAllAgree(): void
    {
        // Arrange
        $client = static::createClient();
        $root = $this->getEventByTitle($client, self::SERIES_ROOT_TITLE);
        $follower = $this->adoptIntoSeries($client, self::FOLLOWER_TITLE, $root, '+14 days');

        // Act
        $crawler = $client->request('GET', '/en/event/' . $follower->getId());

        // Assert
        $this->assertResponseIsSuccessful();
        $canonical = (string) $crawler->filter('link[rel="canonical"]')->attr('href');
        $ogUrl = (string) $crawler->filter('meta[property="og:url"]')->attr('content');
        $jsonLd = json_decode($crawler->filter('script[type="application/ld+json"]')->last()->text(), true);

        static::assertStringEndsWith('/en/event/' . $root->getId(), $canonical);
        static::assertSame($canonical, $ogUrl);
        static::assertContains(
            sprintf('<%s>; rel="canonical"', $canonical),
            $client->getResponse()->headers->all('Link'),
        );
        static::assertSame($canonical, $jsonLd['url'] ?? null);
    }

    public function testMemberCarryingARootMarkerIsSelfCanonicalAndCapturesLaterMembers(): void
    {
        // Arrange
        $client = static::createClient();
        $root = $this->getEventByTitle($client, self::SERIES_ROOT_TITLE);
        $branch = $this->adoptIntoSeries($client, self::BRANCH_TITLE, $root, '+21 days', [
            'en' => 'Karaoke night with a rented machine and a full song catalogue.',
        ]);
        $this->markMember($client, $branch, 'en', EventCanonicalRootType::Root);
        $follower = $this->adoptIntoSeries($client, self::FOLLOWER_TITLE, $root, '+28 days');

        // Act
        $branchCanonical = (string) $client
            ->request('GET', '/en/event/' . $branch->getId())
            ->filter('link[rel="canonical"]')
            ->attr('href');
        $followerCanonical = (string) $client
            ->request('GET', '/en/event/' . $follower->getId())
            ->filter('link[rel="canonical"]')
            ->attr('href');

        // Assert
        static::assertStringEndsWith('/en/event/' . $branch->getId(), $branchCanonical);
        static::assertStringEndsWith('/en/event/' . $branch->getId(), $followerCanonical);
    }

    public function testDetachedMemberIsSelfCanonicalAndDoesNotCaptureLaterMembers(): void
    {
        // Arrange
        $client = static::createClient();
        $root = $this->getEventByTitle($client, self::SERIES_ROOT_TITLE);
        $detached = $this->adoptIntoSeries($client, self::BRANCH_TITLE, $root, '+35 days', [
            'en' => 'Bowling in the city centre this once, no board games at all.',
        ]);
        $this->markMember($client, $detached, 'en', EventCanonicalRootType::Detached);
        $follower = $this->adoptIntoSeries($client, self::FOLLOWER_TITLE, $root, '+42 days');

        // Act
        $detachedCanonical = (string) $client
            ->request('GET', '/en/event/' . $detached->getId())
            ->filter('link[rel="canonical"]')
            ->attr('href');
        $followerCanonical = (string) $client
            ->request('GET', '/en/event/' . $follower->getId())
            ->filter('link[rel="canonical"]')
            ->attr('href');

        // Assert
        static::assertStringEndsWith('/en/event/' . $detached->getId(), $detachedCanonical);
        static::assertStringEndsWith('/en/event/' . $root->getId(), $followerCanonical);
    }

    public function testSitemapListsRootsButNotFollowers(): void
    {
        // Arrange
        $client = static::createClient();
        $root = $this->getEventByTitle($client, self::SERIES_ROOT_TITLE);
        $follower = $this->adoptIntoSeries($client, self::FOLLOWER_TITLE, $root, '+7 days');

        // Act
        $client->request('GET', '/sitemap.xml');

        // Assert
        $this->assertResponseIsSuccessful();
        $xml = (string) $client->getResponse()->getContent();
        static::assertStringContainsString('/en/event/' . $root->getId() . '<', $xml);
        static::assertStringNotContainsString('/en/event/' . $follower->getId() . '<', $xml);
    }

    public function testPastOneOffEventIsSelfCanonicalAndCarriesNoRobotsMeta(): void
    {
        // Arrange
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);
        $oneOff = $this->getEventByTitle($client, self::ONE_OFF_TITLE);
        $oneOff->setStart(new DateTime('-3 months'));
        $em->flush();

        // Act
        $crawler = $client->request('GET', '/en/event/' . $oneOff->getId());

        // Assert
        $this->assertResponseIsSuccessful();
        static::assertSame(0, $crawler->filter('meta[name="robots"]')->count());
        static::assertStringEndsWith(
            '/en/event/' . $oneOff->getId(),
            (string) $crawler->filter('link[rel="canonical"]')->attr('href'),
        );
    }
}
