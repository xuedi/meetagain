<?php declare(strict_types=1);

namespace Tests\Functional\Controller\Admin;

use App\Entity\Cms;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class CmsAddReservedSlugTest extends WebTestCase
{
    private const string ADMIN_EMAIL = 'Admin@example.org';
    private const string ADMIN_PASSWORD = '1234';

    public function testAddingReservedSlugIsRejectedAndNotPersisted(): void
    {
        // Arrange
        $client = static::createClient();
        $this->login($client);
        $em = $client->getContainer()->get(EntityManagerInterface::class);
        $before = $em->getRepository(Cms::class)->count(['slug' => 'imprint']);

        // Act
        $this->submitAddForm($client, 'imprint');

        // Assert
        $this->assertResponseRedirects('/en/admin/cms');
        $em->clear();
        static::assertSame($before, $em->getRepository(Cms::class)->count(['slug' => 'imprint']));
    }

    public function testAddingFreeSlugPersists(): void
    {
        // Arrange
        $client = static::createClient();
        $this->login($client);
        $em = $client->getContainer()->get(EntityManagerInterface::class);
        $slug = 'reserved-slug-test-page';

        // Act
        $this->submitAddForm($client, $slug);

        // Assert
        $this->assertResponseRedirects();
        static::assertStringContainsString('/admin/cms/', (string) $client->getResponse()->headers->get('Location'));
        $em->clear();
        $created = $em->getRepository(Cms::class)->findOneBy(['slug' => $slug]);
        static::assertInstanceOf(Cms::class, $created);

        $em->remove($created);
        $em->flush();
    }

    private function submitAddForm(KernelBrowser $client, string $slug): void
    {
        $client->request('POST', '/en/admin/cms/add', [
            'cms' => ['slug' => $slug],
        ]);
    }

    private function login(KernelBrowser $client): void
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
}
