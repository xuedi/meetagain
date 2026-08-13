<?php declare(strict_types=1);

namespace Tests\Functional\Controller\Admin;

use App\Entity\Location;
use Doctrine\ORM\EntityManagerInterface;
use SensitiveParameter;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class LocationQuickCreateTest extends WebTestCase
{
    private const string ADMIN_EMAIL = 'Admin@example.org';
    private const string MEMBER_EMAIL = 'Adem.Lane@example.org';
    private const string PASSWORD = '1234';
    private const string QUICK_CREATE_URL = '/en/admin/locations/quick-create';

    public function testTheEventFormCarriesTheVenueModalTargetingItsOwnSelect(): void
    {
        // Arrange
        $client = static::createClient();
        $this->login($client, self::ADMIN_EMAIL);

        // Act
        $crawler = $client->request('GET', '/en/admin/events/new');

        // Assert
        $this->assertResponseIsSuccessful();
        $modal = $crawler->filter('#venue-modal');
        static::assertCount(1, $modal);
        static::assertCount(1, $crawler->filter('select#' . $modal->attr('data-target-select')));
    }

    public function testAVenueCreatedFromTheModalComesBackForTheDropdown(): void
    {
        // Arrange
        $client = static::createClient();
        $this->login($client, self::ADMIN_EMAIL);
        $token = $this->venueFormToken($client);

        // Act
        $client->request('POST', self::QUICK_CREATE_URL, $this->venuePayload($token, ['name' => 'Quick Created Venue']));

        // Assert
        $this->assertResponseStatusCodeSame(201);
        $payload = json_decode((string) $client->getResponse()->getContent(), true);
        static::assertSame('Quick Created Venue', $payload['name']);
        static::assertIsInt($payload['id']);

        $em = $this->entityManager($client);
        $created = $em->find(Location::class, $payload['id']);
        static::assertInstanceOf(Location::class, $created);

        $em->remove($created);
        $em->flush();
    }

    public function testABlankNameIsReportedPerFieldAndPersistsNothing(): void
    {
        // Arrange
        $client = static::createClient();
        $this->login($client, self::ADMIN_EMAIL);
        $token = $this->venueFormToken($client);
        $before = $this->venueCount($client);

        // Act
        $client->request('POST', self::QUICK_CREATE_URL, $this->venuePayload($token, ['name' => '']));

        // Assert
        $this->assertResponseStatusCodeSame(422);
        $payload = json_decode((string) $client->getResponse()->getContent(), true);
        static::assertArrayHasKey('name', $payload['errors']);
        static::assertSame($before, $this->venueCount($client));
    }

    public function testAnOverLongCityIsRejectedBeforeItReachesTheColumn(): void
    {
        // Arrange
        $client = static::createClient();
        $this->login($client, self::ADMIN_EMAIL);
        $token = $this->venueFormToken($client);

        // Act
        $client->request('POST', self::QUICK_CREATE_URL, $this->venuePayload($token, ['city' => str_repeat('x', 33)]));

        // Assert
        $this->assertResponseStatusCodeSame(422);
        $payload = json_decode((string) $client->getResponse()->getContent(), true);
        static::assertArrayHasKey('city', $payload['errors']);
    }

    public function testAMissingCsrfTokenIsRejected(): void
    {
        // Arrange
        $client = static::createClient();
        $this->login($client, self::ADMIN_EMAIL);
        $before = $this->venueCount($client);

        // Act
        $client->request('POST', self::QUICK_CREATE_URL, $this->venuePayload('', []));

        // Assert
        $this->assertResponseStatusCodeSame(422);
        static::assertSame($before, $this->venueCount($client));
    }

    public function testAGetRequestNeverReachesTheEndpoint(): void
    {
        // Arrange
        $client = static::createClient();
        $this->login($client, self::ADMIN_EMAIL);
        $before = $this->venueCount($client);

        // Act
        $client->request('GET', self::QUICK_CREATE_URL);

        // Assert
        static::assertFalse($client->getResponse()->isSuccessful());
        static::assertSame($before, $this->venueCount($client));
    }

    public function testAMemberBelowOrganizerCannotCreateVenues(): void
    {
        // Arrange
        $client = static::createClient();
        $this->login($client, self::MEMBER_EMAIL);

        // Act
        $client->request('POST', self::QUICK_CREATE_URL, $this->venuePayload('', []));

        // Assert
        $this->assertResponseStatusCodeSame(403);
    }

    /**
     * @param array<string, string> $overrides
     *
     * @return array<string, array<string, string>>
     */
    private function venuePayload(#[SensitiveParameter] string $token, array $overrides): array
    {
        return [
            'location' => array_merge([
                'name' => 'Modal Venue',
                'description' => 'Created without leaving the event form.',
                'street' => 'Teststrasse 1',
                'city' => 'Berlin',
                'postcode' => '10115',
                'longitude' => '',
                'latitude' => '',
                '_token' => $token,
            ], $overrides),
        ];
    }

    private function venueFormToken(KernelBrowser $client): string
    {
        $crawler = $client->request('GET', '/en/admin/events/new');
        $this->assertResponseIsSuccessful();

        return (string) $crawler->filter('#venue-modal input[name="location[_token]"]')->attr('value');
    }

    private function venueCount(KernelBrowser $client): int
    {
        return (int) $this
            ->entityManager($client)
            ->createQuery('SELECT COUNT(l.id) FROM ' . Location::class . ' l')
            ->getSingleScalarResult();
    }

    private function entityManager(KernelBrowser $client): EntityManagerInterface
    {
        $em = $client->getContainer()->get('doctrine')->getManager();
        assert($em instanceof EntityManagerInterface);

        return $em;
    }

    private function login(KernelBrowser $client, string $email): void
    {
        $crawler = $client->request('GET', '/en/login');
        $form = $crawler
            ->selectButton('Login')
            ->form([
                '_username' => $email,
                '_password' => self::PASSWORD,
            ]);
        $client->submit($form);
        $client->followRedirect();
    }
}
