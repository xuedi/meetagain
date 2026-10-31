<?php declare(strict_types=1);

namespace Module\Geo\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Module\Geo\Internal\Slugs;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Tests\Module\Members;

final class CityControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $container = self::getContainer();
        $places = new Places($container->get(EntityManagerInterface::class), $container->get(Slugs::class));
        $germany = $places->country('DE', 'DEU', 2921044, 'Germany', 82927922, ['de' => 'Deutschland']);
        $usa = $places->country('US', 'USA', 6252001, 'United States', 327167434);
        $places->city($germany, 2950159, 'Berlin', 3426354, ['en' => 'Berlin']);
        $places->city($usa, 5083330, 'Berlin', 10051);
    }

    public function testAGuestIsSentToTheLogin(): void
    {
        // Act
        $this->client->request('GET', '/en/geo/cities?q=ber');

        // Assert
        self::assertResponseRedirects();
    }

    public function testAMemberGetsTheMatchesMostPopulousFirst(): void
    {
        // Arrange
        $this->loginMember();

        // Act
        $this->client->request('GET', '/en/geo/cities?q=ber');

        // Assert
        self::assertSame(['Berlin, Germany', 'Berlin, United States'], array_column($this->json(), 'label'));
    }

    public function testTheSearchIsScopedToTheChosenCountry(): void
    {
        // Arrange
        $this->loginMember();

        // Act
        $this->client->request('GET', '/en/geo/cities?q=ber&country=US');

        // Assert
        self::assertSame(['US'], array_column($this->json(), 'country'));
    }

    public function testNamesFollowThePageLocale(): void
    {
        // Arrange
        $this->loginMember();

        // Act
        $this->client->request('GET', '/de/geo/cities?q=ber&country=DE');

        // Assert
        self::assertSame(['Berlin, Deutschland'], array_column($this->json(), 'label'));
    }

    public function testAQueryTooShortAnswersNothing(): void
    {
        // Arrange
        $this->loginMember();

        // Act
        $this->client->request('GET', '/en/geo/cities?q=b');

        // Assert
        self::assertSame([], $this->json());
    }

    private function loginMember(): void
    {
        $this->client->loginUser(new Members(self::getContainer()->get(EntityManagerInterface::class))->member('Searcher'));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function json(): array
    {
        self::assertResponseIsSuccessful();

        return json_decode((string) $this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
    }
}
