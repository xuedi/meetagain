<?php declare(strict_types=1);

namespace Tests\Functional\Controller\Admin\Seo;

use App\Service\Seo\IndexNowService;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class IndexNowControllerTest extends WebTestCase
{
    private const string ADMIN_EMAIL = 'Admin@example.org';
    private const string ADMIN_PASSWORD = '1234';
    private const string INDEXNOW_PATH = '/en/admin/seo/indexnow';

    /**
     * @return iterable<string, array{0: int, 1: string}>
     */
    public static function submissionOutcomes(): iterable
    {
        yield 'accepted' => [200, 'success'];
        yield 'queued' => [202, 'success'];
        yield 'rejected' => [403, 'error'];
    }

    #[DataProvider('submissionOutcomes')]
    public function testSubmitFlashesTheOutcome(int $status, string $expectedFlashType): void
    {
        // Arrange
        $client = static::createClient();
        $client->disableReboot();
        $this->loginAsAdmin($client);
        $indexNowStub = $this->createStub(IndexNowService::class);
        $indexNowStub->method('getOrCreateKey')->willReturn('testkey');
        $indexNowStub->method('submit')->willReturn(['status' => $status, 'host' => 'example.org']);
        static::getContainer()->set(IndexNowService::class, $indexNowStub);

        $crawler = $client->request('GET', self::INDEXNOW_PATH);
        $token = (string) $crawler->filter('a[data-post][href="' . self::INDEXNOW_PATH . '/submit"]')->attr('data-csrf-token');

        // Act
        $client->request('POST', self::INDEXNOW_PATH . '/submit', ['_token' => $token]);

        // Assert
        $this->assertResponseRedirects();
        self::assertArrayHasKey($expectedFlashType, $client->getRequest()->getSession()->getFlashBag()->all());
    }

    public function testSubmitRejectsAnInvalidCsrfToken(): void
    {
        // Arrange
        $client = static::createClient();
        $this->loginAsAdmin($client);

        // Act
        $client->request('POST', self::INDEXNOW_PATH . '/submit', ['_token' => 'invalid']);

        // Assert
        $this->assertResponseStatusCodeSame(400);
    }

    private function loginAsAdmin(KernelBrowser $client): void
    {
        $crawler = $client->request('GET', '/en/login');
        $form = $crawler->selectButton('Login')->form([
            '_username' => self::ADMIN_EMAIL,
            '_password' => self::ADMIN_PASSWORD,
        ]);
        $client->submit($form);
        $client->followRedirect();
    }
}
