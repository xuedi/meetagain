<?php declare(strict_types=1);

namespace Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Functional tests for the login click path.
 *
 * Uses fixture credentials:
 * - Admin: Admin@example.org / 1234
 * - Crystal Liu: Crystal.Liu@example.org / 1234
 */
class LoginTest extends WebTestCase
{
    private const ADMIN_EMAIL = 'Admin@example.org';
    private const ADMIN_PASSWORD = '1234';

    public function testLoginPageLoads(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/en/login');

        $this->assertResponseIsSuccessful();
        $this->assertGreaterThan(0, $crawler->filter('form')->count(), 'Login form should exist');
        $this->assertGreaterThan(0, $crawler->filter('input[name="_username"]')->count(), 'Username field should exist');
        $this->assertGreaterThan(0, $crawler->filter('input[name="_password"]')->count(), 'Password field should exist');
    }

    public function testLoginWithValidCredentials(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/en/login');
        $this->assertResponseIsSuccessful();

        $form = $crawler->selectButton('Login')->form([
            '_username' => self::ADMIN_EMAIL,
            '_password' => self::ADMIN_PASSWORD,
        ]);

        $client->submit($form);

        $this->assertResponseRedirects();
    }

    public function testLoginWithInvalidCredentials(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/en/login');

        $form = $crawler->selectButton('Login')->form([
            '_username' => 'invalid@example.org',
            '_password' => 'wrongpassword',
        ]);

        $client->submit($form);

        $this->assertResponseRedirects();

        $crawler = $client->followRedirect();
        $this->assertGreaterThan(0, $crawler->filter('.alert')->count(), 'Error alert should be shown');
    }

    public function testLoginWithEmptyCredentials(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/en/login');

        $form = $crawler->selectButton('Login')->form([
            '_username' => '',
            '_password' => '',
        ]);

        $client->submit($form);

        $this->assertResponseRedirects();
    }

    public function testAccessProtectedPageWithoutLogin(): void
    {
        $client = static::createClient();

        $client->request('GET', '/en/profile/');

        $this->assertResponseRedirects();
    }

    public function testAccessProfileAfterLogin(): void
    {
        $client = static::createClient();

        // Login
        $crawler = $client->request('GET', '/en/login');
        $form = $crawler->selectButton('Login')->form([
            '_username' => self::ADMIN_EMAIL,
            '_password' => self::ADMIN_PASSWORD,
        ]);
        $client->submit($form);
        $client->followRedirect();

        // Access profile (with trailing slash)
        $client->request('GET', '/en/profile/');
        $this->assertResponseIsSuccessful();
    }

    public function testLogoutAfterLogin(): void
    {
        $client = static::createClient();

        // Login first
        $crawler = $client->request('GET', '/en/login');
        $form = $crawler->selectButton('Login')->form([
            '_username' => self::ADMIN_EMAIL,
            '_password' => self::ADMIN_PASSWORD,
        ]);
        $client->submit($form);
        $client->followRedirect();

        // Logout
        $client->request('GET', '/en/logout');
        $this->assertResponseRedirects();

        // Profile should now redirect to login
        $client->followRedirect();
        $client->request('GET', '/en/profile/');
        $this->assertResponseRedirects();
    }

    public function testRegisterPageLoads(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/en/register');

        $this->assertResponseIsSuccessful();
        $this->assertGreaterThan(0, $crawler->filter('form')->count(), 'Register form should exist');
    }

    public function testPasswordResetPageLoads(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/en/reset');

        $this->assertResponseIsSuccessful();
        $this->assertGreaterThan(0, $crawler->filter('form')->count(), 'Reset form should exist');
    }
}
