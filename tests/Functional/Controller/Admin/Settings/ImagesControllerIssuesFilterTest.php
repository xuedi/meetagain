<?php declare(strict_types=1);

namespace Tests\Functional\Controller\Admin\Settings;

use App\Entity\Image;
use App\Entity\User;
use App\Enum\ImageReportReason;
use App\Enum\ImageType;
use App\Repository\UserRepository;
use App\Service\Config\LanguageService;
use App\Service\Media\ImageAltStatusCache;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

final class ImagesControllerIssuesFilterTest extends WebTestCase
{
    private const string ADMIN_EMAIL = 'Admin@example.org';
    private const string ADMIN_PASSWORD = '1234';

    public function testEachIssuesOptionShowsOnlyMatchingRows(): void
    {
        $client = static::createClient();
        $this->loginAsAdmin($client);
        $ids = $this->seedImages();

        $crawler = $client->request('GET', '/en/admin/system/images');
        $this->assertResponseIsSuccessful();
        foreach ($ids as $id) {
            $this->assertRowPresence($crawler, $id, true);
        }

        $expectations = [
            'healthy' => ['healthy' => true, 'missingAlt' => false, 'missingAttribution' => false, 'reported' => false],
            'missing_alt' => ['healthy' => false, 'missingAlt' => true, 'missingAttribution' => false, 'reported' => false],
            'missing_attribution' => ['healthy' => false, 'missingAlt' => false, 'missingAttribution' => true, 'reported' => false],
            'reported' => ['healthy' => false, 'missingAlt' => false, 'missingAttribution' => false, 'reported' => true],
        ];
        foreach ($expectations as $filter => $rows) {
            $crawler = $client->request('GET', '/en/admin/system/images?issues=' . $filter);
            $this->assertResponseIsSuccessful();
            foreach ($rows as $seed => $present) {
                $this->assertRowPresence($crawler, $ids[$seed], $present, $filter);
            }
        }
    }

    public function testRowMarkerRendersOnceWithIssueLabelsAsTooltip(): void
    {
        $client = static::createClient();
        $this->loginAsAdmin($client);
        $ids = $this->seedImages();

        $crawler = $client->request('GET', '/en/admin/system/images');
        $this->assertResponseIsSuccessful();

        static::assertSame(0, $this->rowMarker($crawler, $ids['healthy'])->count());
        static::assertSame('Missing ALT', $this->rowMarker($crawler, $ids['missingAlt'])->attr('title'));
        static::assertSame('Missing attribution', $this->rowMarker($crawler, $ids['missingAttribution'])->attr('title'));
        static::assertSame('Reported', $this->rowMarker($crawler, $ids['reported'])->attr('title'));
    }

    public function testDropdownOptionUrlsPreserveTheOtherActiveFilters(): void
    {
        $client = static::createClient();
        $this->loginAsAdmin($client);
        $this->seedImages();

        $crawler = $client->request('GET', '/en/admin/system/images?issues=missing_alt&range=1y');
        $this->assertResponseIsSuccessful();

        $hrefs = $crawler->filter('a[href*="/admin/system/images"]')->each(
            static fn(Crawler $node): string => (string) $node->attr('href'),
        );

        $locationOption = array_filter($hrefs, static fn(string $href): bool => str_contains($href, 'location='));
        static::assertNotEmpty($locationOption);
        foreach ($locationOption as $href) {
            static::assertStringContainsString('issues=missing_alt', $href);
            static::assertStringContainsString('range=1y', $href);
        }

        $reportedOption = array_filter($hrefs, static fn(string $href): bool => str_contains($href, 'issues=reported'));
        static::assertNotEmpty($reportedOption);
        foreach ($reportedOption as $href) {
            static::assertStringContainsString('range=1y', $href);
        }

        $rangeOption = array_filter($hrefs, static fn(string $href): bool => str_contains($href, 'range=1m'));
        static::assertNotEmpty($rangeOption);
        foreach ($rangeOption as $href) {
            static::assertStringContainsString('issues=missing_alt', $href);
        }
    }

    public function testSavingAllAltsMovesTheImageFromMissingAltToHealthy(): void
    {
        $client = static::createClient();
        $this->loginAsAdmin($client);
        $ids = $this->seedImages();
        $id = $ids['missingAlt'];

        $crawler = $client->request('GET', '/en/admin/system/images?issues=missing_alt');
        $this->assertRowPresence($crawler, $id, true);

        $crawler = $client->request('GET', sprintf('/en/admin/system/images/%d', $id));
        $this->assertResponseIsSuccessful();
        $form = $crawler->filter('form[action$="/alt"]')->first();
        $action = (string) $form->attr('action');
        $token = (string) $form->filter('input[name="_token"]')->attr('value');

        $language = self::getContainer()->get(LanguageService::class);
        foreach ($language->getEnabledCodes() as $code) {
            $client->request('POST', $action, ['_token' => $token, 'locale' => $code, 'alt' => 'alt ' . $code]);
            $this->assertResponseRedirects();
        }

        $crawler = $client->request('GET', '/en/admin/system/images?issues=missing_alt');
        $this->assertRowPresence($crawler, $id, false);
        $crawler = $client->request('GET', '/en/admin/system/images?issues=healthy');
        $this->assertRowPresence($crawler, $id, true);
    }

    /**
     * @return array{healthy: int, missingAlt: int, missingAttribution: int, reported: int}
     */
    private function seedImages(): array
    {
        $container = self::getContainer();
        $container->get(ImageAltStatusCache::class)->invalidateAll();

        $uploader = $container->get(UserRepository::class)->findOneBy([]);
        static::assertInstanceOf(User::class, $uploader);

        $language = $container->get(LanguageService::class);
        $codes = $language->getEnabledCodes();
        $sourceLocale = $language->getFilteredDefaultLocale();

        $healthy = self::newImage($uploader);
        self::fillAlts($healthy, $codes, $sourceLocale);
        $healthy->setAttributionNotRequired(true);

        $missingAlt = self::newImage($uploader);
        $missingAlt->setAttributionNotRequired(true);

        $missingAttribution = self::newImage($uploader);
        self::fillAlts($missingAttribution, $codes, $sourceLocale);

        $reported = self::newImage($uploader);
        self::fillAlts($reported, $codes, $sourceLocale);
        $reported->setAttributionNotRequired(true);
        $reported->setReported(ImageReportReason::Privacy);

        $em = $container->get(EntityManagerInterface::class);
        foreach ([$healthy, $missingAlt, $missingAttribution, $reported] as $image) {
            $em->persist($image);
        }
        $em->flush();

        return [
            'healthy' => (int) $healthy->getId(),
            'missingAlt' => (int) $missingAlt->getId(),
            'missingAttribution' => (int) $missingAttribution->getId(),
            'reported' => (int) $reported->getId(),
        ];
    }

    private static function newImage(User $uploader): Image
    {
        $image = new Image();
        $image->setMimeType('image/jpeg');
        $image->setExtension('jpg');
        $image->setSize(1234);
        $image->setHash(bin2hex(random_bytes(16)));
        $image->setUploader($uploader);
        $image->setCreatedAt(new DateTimeImmutable());
        $image->setType(ImageType::CmsBlock);

        return $image;
    }

    /**
     * @param list<string> $codes
     */
    private static function fillAlts(Image $image, array $codes, string $sourceLocale): void
    {
        foreach ($codes as $code) {
            if ($code === $sourceLocale) {
                $image->setAlt('alt ' . $code);
            } else {
                $image->setAltTranslation($code, 'alt ' . $code);
            }
        }
    }

    private function assertRowPresence(Crawler $crawler, int $id, bool $present, string $context = ''): void
    {
        $count = $crawler->filter(sprintf('a[href="/en/admin/system/images/%d"]', $id))->count();
        $message = sprintf('Image %d should%s be listed%s', $id, $present ? '' : ' not', $context !== '' ? " for issues={$context}" : '');
        if ($present) {
            static::assertGreaterThan(0, $count, $message);
        } else {
            static::assertSame(0, $count, $message);
        }
    }

    private function rowMarker(Crawler $crawler, int $id): Crawler
    {
        return $crawler
            ->filterXPath(sprintf('//tr[.//a[@href="/en/admin/system/images/%d"]]', $id))
            ->filter('span.has-text-warning[title]');
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
