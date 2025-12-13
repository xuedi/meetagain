<?php

declare(strict_types=1);

namespace Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class EventsPageTest extends WebTestCase
{
    private const ADMIN_EMAIL = 'Admin@example.org';
    private const ADMIN_PASSWORD = '1234';
    private const USER_EMAIL = 'Crystal.Liu@example.org';
    private const USER_PASSWORD = '1234';

    public function testEventsPageLoadsForAnonymousUser(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/en/events');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form', 'Filter form should exist');
    }

    public function testEventsPageLoadsForLoggedInUser(): void
    {
        $client = static::createClient();

        $this->login($client, self::USER_EMAIL, self::USER_PASSWORD);

        $crawler = $client->request('GET', '/en/events');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form', 'Filter form should exist');
    }

    public function testEventsFilterFormSubmission(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/en/events');
        $this->assertResponseIsSuccessful();

        // Submit filter form if it exists
        $forms = $crawler->filter('form');
        if ($forms->count() > 0) {
            $form = $forms->first()->form();
            $client->submit($form);
            $this->assertResponseIsSuccessful();
        }
    }

    public function testEventDetailsPageLoadsForAnonymousUser(): void
    {
        $client = static::createClient();

        // Event ID 1 should exist from fixtures
        $crawler = $client->request('GET', '/en/event/1');

        $this->assertResponseIsSuccessful();
    }

    public function testEventDetailsPageLoadsForLoggedInUser(): void
    {
        $client = static::createClient();

        $this->login($client, self::USER_EMAIL, self::USER_PASSWORD);

        $crawler = $client->request('GET', '/en/event/1');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form', 'Comment form should exist for logged in users');
    }

    public function testFeaturedEventsPageLoads(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/en/event/featured/');

        $this->assertResponseIsSuccessful();
    }

    public function testToggleRsvpRequiresLogin(): void
    {
        $client = static::createClient();

        $client->request('GET', '/en/event/toggleRsvp/1/');

        $this->assertResponseRedirects();
    }

    public function testToggleRsvpWorksForLoggedInUser(): void
    {
        $client = static::createClient();

        $this->login($client, self::USER_EMAIL, self::USER_PASSWORD);

        $client->request('GET', '/en/event/toggleRsvp/1/');

        $this->assertResponseRedirects();
    }

    public function testEventUploadPageRequiresLogin(): void
    {
        $client = static::createClient();

        $client->request('GET', '/en/event/upload/1');

        $this->assertResponseRedirects();
    }

    public function testEventUploadPageLoadsForLoggedInUser(): void
    {
        $client = static::createClient();

        $this->login($client, self::USER_EMAIL, self::USER_PASSWORD);

        $crawler = $client->request('GET', '/en/event/upload/1');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form', 'Upload form should exist');
    }

    public function testCommentFormExistsForLoggedInUser(): void
    {
        $client = static::createClient();

        $this->login($client, self::USER_EMAIL, self::USER_PASSWORD);

        $crawler = $client->request('GET', '/en/event/1');
        $this->assertResponseIsSuccessful();

        // Verify comment form exists for logged-in users
        $this->assertSelectorExists('form', 'Comment form should exist for logged in users');
        $this->assertSelectorExists('textarea', 'Comment textarea should exist');
    }

    public function testNonExistentEventReturns404Or500(): void
    {
        $client = static::createClient();

        $client->request('GET', '/en/event/99999');

        // Event not found should result in error (404 or 500 if null check fails)
        $this->assertTrue(
            $client->getResponse()->getStatusCode() >= 400,
            'Non-existent event should return error status'
        );
    }

    public function testDeleteCommentRedirectsBack(): void
    {
        $client = static::createClient();

        $this->login($client, self::USER_EMAIL, self::USER_PASSWORD);

        // Try to delete a non-existent comment on existing event - should redirect back
        $client->request('GET', '/en/event/1/deleteComment/999');

        $this->assertResponseRedirects();
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
