<?php declare(strict_types=1);

namespace Tests\Functional\Controller\Admin\Settings;

use App\Entity\Image;
use App\Repository\ImageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ImagesControllerAltTest extends WebTestCase
{
    private const string ADMIN_EMAIL = 'Admin@example.org';
    private const string ADMIN_PASSWORD = '1234';

    public function testUpdateAltWritesBaseForSourceLocaleAndMapForOtherLocales(): void
    {
        $client = static::createClient();
        $this->loginAsAdmin($client);

        $id = $this->firstImageId();
        if ($id === null) {
            static::markTestSkipped('No images in fixtures.');
        }

        [$action, $token] = $this->altFormFor($client, $id);

        // Source locale (en) writes the base alt column.
        $client->request('POST', $action, ['_token' => $token, 'locale' => 'en', 'alt' => 'English alt']);
        $this->assertResponseRedirects();

        // Non-source locale (de) writes into the translations map, not the base column.
        $client->request('POST', $action, ['_token' => $token, 'locale' => 'de', 'alt' => 'Deutscher Alt']);
        $this->assertResponseRedirects();

        $image = $this->reloadImage($id);
        static::assertSame('English alt', $image->getAlt());
        static::assertSame('Deutscher Alt', $image->getAltTranslation('de'));
    }

    public function testUpdateAltIgnoresLocaleOutsideEnabledSet(): void
    {
        $client = static::createClient();
        $this->loginAsAdmin($client);

        $id = $this->firstImageId();
        if ($id === null) {
            static::markTestSkipped('No images in fixtures.');
        }

        [$action, $token] = $this->altFormFor($client, $id);

        $client->request('POST', $action, ['_token' => $token, 'locale' => 'xx', 'alt' => 'should be ignored']);
        $this->assertResponseRedirects();

        $image = $this->reloadImage($id);
        static::assertNull($image->getAltTranslation('xx'));
        static::assertNotSame('should be ignored', $image->getAlt());
    }

    public function testUpdateAltInvalidatesTheAltStatusCacheEntry(): void
    {
        $client = static::createClient();
        // The pool is in-memory in tests; a reboot between requests would detach it from $pool.
        $client->disableReboot();
        $this->loginAsAdmin($client);

        $id = $this->firstImageId();
        if ($id === null) {
            static::markTestSkipped('No images in fixtures.');
        }

        $pool = self::getContainer()->get('cache.image_alt_status');
        $pool->clear();

        // The admin list warms the per-image status entry.
        $client->request('GET', '/en/admin/system/images');
        $this->assertResponseIsSuccessful();
        static::assertTrue($pool->getItem('image_alt_status.' . $id)->isHit());

        [$action, $token] = $this->altFormFor($client, $id);
        $client->request('POST', $action, ['_token' => $token, 'locale' => 'en', 'alt' => 'cache buster']);
        $this->assertResponseRedirects();

        static::assertFalse($pool->getItem('image_alt_status.' . $id)->isHit());
    }

    /**
     * @return array{string, string} action URL and CSRF token, shared by every per-locale alt form
     */
    private function altFormFor(KernelBrowser $client, int $id): array
    {
        $crawler = $client->request('GET', "/en/admin/system/images/{$id}");
        $this->assertResponseIsSuccessful();
        $form = $crawler->filter('form[action$="/alt"]')->first();
        static::assertGreaterThan(0, $form->count(), 'Per-locale alt form should render.');

        return [(string) $form->attr('action'), (string) $form->filter('input[name="_token"]')->attr('value')];
    }

    private function firstImageId(): ?int
    {
        return self::getContainer()->get(ImageRepository::class)->findOneBy([])?->getId();
    }

    private function reloadImage(int $id): Image
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        $image = self::getContainer()->get(ImageRepository::class)->find($id);
        static::assertInstanceOf(Image::class, $image);

        return $image;
    }

    private function loginAsAdmin(KernelBrowser $client): void
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
