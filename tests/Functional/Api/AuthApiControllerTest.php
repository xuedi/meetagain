<?php declare(strict_types=1);

namespace Tests\Functional\Api;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Functional tests for the Bearer token auth API endpoints.
 *
 * Uses fixture credentials:
 * - Admin: Admin@example.org / 1234
 */
class AuthApiControllerTest extends WebTestCase
{
    private const string ADMIN_EMAIL = 'Admin@example.org';
    private const string ADMIN_PASSWORD = '1234';

    public function testTokenGenerationWithValidCredentials(): void
    {
        // Arrange
        $client = static::createClient();

        // Act
        $client->request('POST', '/api/auth/token', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'email' => self::ADMIN_EMAIL,
            'password' => self::ADMIN_PASSWORD,
        ]));

        // Assert
        $this->assertResponseIsSuccessful();
        $data = json_decode((string) $client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('token', $data);
        $this->assertNotEmpty($data['token']);
    }

    public function testTokenGenerationWithInvalidPassword(): void
    {
        // Arrange
        $client = static::createClient();

        // Act
        $client->request('POST', '/api/auth/token', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'email' => self::ADMIN_EMAIL,
            'password' => 'wrong-password',
        ]));

        // Assert
        $this->assertResponseStatusCodeSame(401);
        $data = json_decode((string) $client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('error', $data);
    }

    public function testTokenGenerationWithUnknownEmail(): void
    {
        // Arrange
        $client = static::createClient();

        // Act
        $client->request('POST', '/api/auth/token', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'email' => 'nobody@example.org',
            'password' => 'anything',
        ]));

        // Assert
        $this->assertResponseStatusCodeSame(401);
    }

    public function testTokenRevocationWithValidToken(): void
    {
        // Arrange: generate a token first
        $client = static::createClient();
        $client->request('POST', '/api/auth/token', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'email' => self::ADMIN_EMAIL,
            'password' => self::ADMIN_PASSWORD,
        ]));
        $token = json_decode((string) $client->getResponse()->getContent(), true)['token'];

        // Act
        $client->request('DELETE', '/api/auth/token', [], [], ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]);

        // Assert
        $this->assertResponseStatusCodeSame(204);

        // Verify token hash was cleared
        $em = $client->getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        $user = $client->getContainer()->get(UserRepository::class)->findOneBy(['email' => self::ADMIN_EMAIL]);
        $this->assertNull($user->getApiTokenHash());
    }

    public function testTokenRevocationWithNoToken(): void
    {
        // Arrange
        $client = static::createClient();

        // Act — no Authorization header
        $client->request('DELETE', '/api/auth/token');

        // Assert
        $this->assertResponseStatusCodeSame(401);
    }

    public function testTokenRevocationWithInvalidToken(): void
    {
        // Arrange
        $client = static::createClient();

        // Act
        $client->request('DELETE', '/api/auth/token', [], [], ['HTTP_AUTHORIZATION' => 'Bearer invalid-token-value']);

        // Assert
        $this->assertResponseStatusCodeSame(401);
    }
}
