<?php declare(strict_types=1);

namespace Tests\Functional\Api;

use App\Entity\CmsBlockTypes;
use App\Entity\User;
use App\Repository\UserRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Functional tests for the CMS CRUD API endpoints.
 *
 * All endpoints require ROLE_ADMIN with Bearer token auth.
 */
class CmsCrudApiControllerTest extends WebTestCase
{
    private const string ADMIN_EMAIL = 'Admin@example.org';
    private const string ADMIN_PASSWORD = '1234';

    private function getAdminToken(): array
    {
        $client = static::createClient();
        $client->request('POST', '/api/auth/token', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'email' => self::ADMIN_EMAIL,
            'password' => self::ADMIN_PASSWORD,
        ]));

        $token = json_decode((string) $client->getResponse()->getContent(), true)['token'];

        return [$client, $token];
    }

    public function testListCmsPagesRequiresAuth(): void
    {
        // Arrange
        $client = static::createClient();

        // Act — no auth header
        $client->request('GET', '/api/cms/');

        // Assert
        $this->assertResponseStatusCodeSame(401);
    }

    public function testListCmsPages(): void
    {
        // Arrange
        [$client, $token] = $this->getAdminToken();

        // Act
        $client->request('GET', '/api/cms/', [], [], ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]);

        // Assert
        $this->assertResponseIsSuccessful();
        $data = json_decode((string) $client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
        $this->assertNotEmpty($data);

        $first = $data[0];
        $this->assertArrayHasKey('id', $first);
        $this->assertArrayHasKey('slug', $first);
        $this->assertArrayHasKey('published', $first);
        $this->assertArrayHasKey('titles', $first);
    }

    public function testGetSingleCmsPage(): void
    {
        // Arrange
        [$client, $token] = $this->getAdminToken();

        // Get the list first to find a valid ID
        $client->request('GET', '/api/cms/', [], [], ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]);
        $pages = json_decode((string) $client->getResponse()->getContent(), true);
        $pageId = $pages[0]['id'];

        // Act
        $client->request('GET', '/api/cms/' . $pageId, [], [], ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]);

        // Assert
        $this->assertResponseIsSuccessful();
        $data = json_decode((string) $client->getResponse()->getContent(), true);
        $this->assertSame($pageId, $data['id']);
        $this->assertArrayHasKey('blocks', $data);
        $this->assertArrayHasKey('linkNames', $data);
    }

    public function testGetNonexistentCmsPage(): void
    {
        // Arrange
        [$client, $token] = $this->getAdminToken();

        // Act
        $client->request('GET', '/api/cms/99999', [], [], ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]);

        // Assert
        $this->assertResponseStatusCodeSame(404);
    }

    public function testCreateCmsPage(): void
    {
        // Arrange
        [$client, $token] = $this->getAdminToken();
        $slug = 'api-test-page-' . time();

        // Act
        $client->request(
            'POST',
            '/api/cms/',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer ' . $token],
            json_encode([
                'slug' => $slug,
                'titles' => ['en' => 'API Test Page', 'de' => 'API Testseite'],
                'linkNames' => ['en' => 'Test', 'de' => 'Test'],
            ]),
        );

        // Assert
        $this->assertResponseStatusCodeSame(201);
        $data = json_decode((string) $client->getResponse()->getContent(), true);
        $this->assertSame($slug, $data['slug']);
        $this->assertFalse($data['published']);
        $this->assertSame('API Test Page', $data['titles']['en'] ?? null);
    }

    public function testCreateCmsPageWithoutSlugFails(): void
    {
        // Arrange
        [$client, $token] = $this->getAdminToken();

        // Act
        $client->request(
            'POST',
            '/api/cms/',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer ' . $token],
            json_encode(['titles' => ['en' => 'No Slug Page']]),
        );

        // Assert
        $this->assertResponseStatusCodeSame(400);
    }

    public function testUpdateCmsPage(): void
    {
        // Arrange — create a page first
        [$client, $token] = $this->getAdminToken();
        $slug = 'api-update-test-' . time();

        $client->request(
            'POST',
            '/api/cms/',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer ' . $token],
            json_encode(['slug' => $slug, 'titles' => ['en' => 'Original Title']]),
        );
        $pageId = json_decode((string) $client->getResponse()->getContent(), true)['id'];

        // Act
        $client->request(
            'PUT',
            '/api/cms/' . $pageId,
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer ' . $token],
            json_encode([
                'published' => true,
                'titles' => ['en' => 'Updated Title'],
            ]),
        );

        // Assert
        $this->assertResponseIsSuccessful();
        $data = json_decode((string) $client->getResponse()->getContent(), true);
        $this->assertTrue($data['published']);
        $this->assertSame('Updated Title', $data['titles']['en'] ?? null);
    }

    public function testDeleteCmsPage(): void
    {
        // Arrange — create a disposable page
        [$client, $token] = $this->getAdminToken();
        $slug = 'api-delete-test-' . time();

        $client->request(
            'POST',
            '/api/cms/',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer ' . $token],
            json_encode(['slug' => $slug]),
        );
        $pageId = json_decode((string) $client->getResponse()->getContent(), true)['id'];

        // Act
        $client->request('DELETE', '/api/cms/' . $pageId, [], [], ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]);

        // Assert
        $this->assertResponseStatusCodeSame(204);

        // Verify deletion
        $client->request('GET', '/api/cms/' . $pageId, [], [], ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]);
        $this->assertResponseStatusCodeSame(404);
    }

    public function testDeleteLockedCmsPageFails(): void
    {
        // Arrange — find a locked page (privacy or imprint fixture pages are locked)
        [$client, $token] = $this->getAdminToken();

        $client->request('GET', '/api/cms/', [], [], ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]);
        $pages = json_decode((string) $client->getResponse()->getContent(), true);
        $lockedPage = array_filter($pages, static fn(array $p): bool => $p['locked'] === true);
        $this->assertNotEmpty($lockedPage, 'There should be at least one locked CMS page from fixtures');
        $lockedId = array_values($lockedPage)[0]['id'];

        // Act
        $client->request('DELETE', '/api/cms/' . $lockedId, [], [], ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]);

        // Assert
        $this->assertResponseStatusCodeSame(403);
    }

    public function testAddBlock(): void
    {
        // Arrange — create a page
        [$client, $token] = $this->getAdminToken();
        $slug = 'api-block-test-' . time();

        $client->request(
            'POST',
            '/api/cms/',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer ' . $token],
            json_encode(['slug' => $slug]),
        );
        $pageId = json_decode((string) $client->getResponse()->getContent(), true)['id'];

        // Act
        $client->request(
            'POST',
            '/api/cms/' . $pageId . '/blocks',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer ' . $token],
            json_encode([
                'language' => 'en',
                'type' => CmsBlockTypes::Headline->value,
                'priority' => 1.0,
                'json' => ['text' => 'Hello API'],
            ]),
        );

        // Assert
        $this->assertResponseStatusCodeSame(201);
        $data = json_decode((string) $client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('id', $data);
        $this->assertSame('en', $data['language']);
        $this->assertSame(CmsBlockTypes::Headline->value, $data['type']);
    }

    public function testUpdateBlock(): void
    {
        // Arrange — create page and add a block
        [$client, $token] = $this->getAdminToken();
        $slug = 'api-block-update-' . time();

        $client->request(
            'POST',
            '/api/cms/',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer ' . $token],
            json_encode(['slug' => $slug]),
        );
        $pageId = json_decode((string) $client->getResponse()->getContent(), true)['id'];

        $client->request(
            'POST',
            '/api/cms/' . $pageId . '/blocks',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer ' . $token],
            json_encode([
                'language' => 'en',
                'type' => CmsBlockTypes::Headline->value,
                'priority' => 1.0,
                'json' => ['text' => 'Original'],
            ]),
        );
        $blockId = json_decode((string) $client->getResponse()->getContent(), true)['id'];

        // Act
        $client->request(
            'PUT',
            '/api/cms/' . $pageId . '/blocks/' . $blockId,
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer ' . $token],
            json_encode(['json' => ['text' => 'Updated'], 'priority' => 2.0]),
        );

        // Assert
        $this->assertResponseIsSuccessful();
        $data = json_decode((string) $client->getResponse()->getContent(), true);
        $this->assertEquals(2.0, (float) $data['priority']);
        $this->assertSame(['text' => 'Updated'], $data['json']);
    }

    public function testDeleteBlock(): void
    {
        // Arrange — create page and add a block
        [$client, $token] = $this->getAdminToken();
        $slug = 'api-block-delete-' . time();

        $client->request(
            'POST',
            '/api/cms/',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer ' . $token],
            json_encode(['slug' => $slug]),
        );
        $pageId = json_decode((string) $client->getResponse()->getContent(), true)['id'];

        $client->request(
            'POST',
            '/api/cms/' . $pageId . '/blocks',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer ' . $token],
            json_encode([
                'language' => 'en',
                'type' => CmsBlockTypes::Headline->value,
                'priority' => 1.0,
                'json' => ['text' => 'To delete'],
            ]),
        );
        $blockId = json_decode((string) $client->getResponse()->getContent(), true)['id'];

        // Act
        $client->request(
            'DELETE',
            '/api/cms/' . $pageId . '/blocks/' . $blockId,
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $token],
        );

        // Assert
        $this->assertResponseStatusCodeSame(204);
    }
}
