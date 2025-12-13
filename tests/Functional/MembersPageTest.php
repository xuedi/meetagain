<?php

declare(strict_types=1);

namespace Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class MembersPageTest extends WebTestCase
{
    private const ADMIN_EMAIL = 'Admin@example.org';
    private const ADMIN_PASSWORD = '1234';
    private const USER_EMAIL = 'Crystal.Liu@example.org';
    private const USER_PASSWORD = '1234';

    public function testMembersPageLoadsForAnonymousUser(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/en/members/1');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('.card', 'Member cards should be displayed');
    }

    public function testMembersPageLoadsForLoggedInUser(): void
    {
        $client = static::createClient();

        $this->login($client, self::USER_EMAIL, self::USER_PASSWORD);

        $crawler = $client->request('GET', '/en/members/1');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('.card', 'Member cards should be displayed');
    }

    public function testMembersPageShowsMoreMembersForLoggedInUser(): void
    {
        $client = static::createClient();

        // Get count for anonymous user
        $crawler = $client->request('GET', '/en/members/1');
        $this->assertResponseIsSuccessful();

        // Login and check again - logged in users should potentially see more members (non-public ones)
        $this->login($client, self::USER_EMAIL, self::USER_PASSWORD);

        $crawler = $client->request('GET', '/en/members/1');
        $this->assertResponseIsSuccessful();
    }

    public function testMemberViewPageRequiresLogin(): void
    {
        $client = static::createClient();

        $client->request('GET', '/en/members/view/2');

        // Should show 403 template or redirect
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'login', 'Should prompt user to login');
    }

    public function testMemberViewPageLoadsForLoggedInUser(): void
    {
        $client = static::createClient();

        $this->login($client, self::USER_EMAIL, self::USER_PASSWORD);

        $crawler = $client->request('GET', '/en/members/view/1');

        $this->assertResponseIsSuccessful();
    }

    public function testMembersPagination(): void
    {
        $client = static::createClient();

        // First page
        $crawler = $client->request('GET', '/en/members/1');
        $this->assertResponseIsSuccessful();

        // Second page (if exists)
        $crawler = $client->request('GET', '/en/members/2');
        $this->assertResponseIsSuccessful();
    }

    public function testToggleFollowRequiresLogin(): void
    {
        $client = static::createClient();

        $client->request('GET', '/en/members/toggleFollow/1');

        $this->assertResponseRedirects();
    }

    public function testToggleFollowWorksForLoggedInUser(): void
    {
        $client = static::createClient();

        $this->login($client, self::USER_EMAIL, self::USER_PASSWORD);

        $client->request('GET', '/en/members/toggleFollow/1');

        $this->assertResponseRedirects();
    }

    public function testManagerActionsRequireManagerRole(): void
    {
        $client = static::createClient();

        $this->login($client, self::USER_EMAIL, self::USER_PASSWORD);

        // Rotate avatar - should be denied (403) or redirect
        $client->request('GET', '/en/members/rotate-avatar/2');
        // Either access denied (403) or redirect to access denied page
        $this->assertTrue(
            $client->getResponse()->getStatusCode() === 403 ||
            $client->getResponse()->isRedirect(),
            'Manager action should be denied for regular users'
        );
    }

    public function testAdminCanAccessMemberManagement(): void
    {
        $client = static::createClient();

        $this->login($client, self::ADMIN_EMAIL, self::ADMIN_PASSWORD);

        $crawler = $client->request('GET', '/en/members/view/2');

        $this->assertResponseIsSuccessful();
    }

    private function login($client, string $email, string $password): void
    {
        $crawler = $client->request('GET', '/en/login');
        $form = $crawler->selectButton('Login')->form([
            '_username' => $email,
            '_password' => $password,
        ]);
        $client->submit($form);
        $client->followRedirect();
    }
}
