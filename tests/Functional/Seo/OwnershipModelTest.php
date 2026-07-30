<?php declare(strict_types=1);

namespace Tests\Functional\Seo;

use App\Service\Config\ConfigService;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class OwnershipModelTest extends WebTestCase
{
    private const int MAX_URLS_WALKED = 60;

    public function testEveryAdvertisedUrlAnswersOnTheHostThatAdvertisesIt(): void
    {
        // Arrange
        $client = static::createClient();
        $host = $this->configuredHost();
        $locs = $this->feedLocs($client, $host);
        static::assertNotEmpty($locs, 'the feed advertises nothing');

        // Act
        $failures = [];
        foreach ($this->sample($locs) as $loc) {
            $status = $this->statusOf($client, $host, $loc);
            if ($status !== 200) {
                $failures[] = sprintf('%s answers %d', $loc, $status);
            }
        }

        // Assert
        static::assertSame([], $failures);
    }

    public function testEveryAdvertisedUrlDeclaresItselfCanonical(): void
    {
        // Arrange
        $client = static::createClient();
        $host = $this->configuredHost();
        $locs = $this->feedLocs($client, $host);
        static::assertNotEmpty($locs, 'the feed advertises nothing');

        // Act
        $failures = [];
        foreach ($this->sample($locs) as $loc) {
            $canonical = $this->canonicalOf($client, $host, $loc);
            if ($canonical === null) {
                $failures[] = sprintf('%s declares no canonical', $loc);
                continue;
            }

            if ($this->identity($canonical) !== $this->identity($loc)) {
                $failures[] = sprintf('%s declares %s canonical', $loc, $canonical);
            }
        }

        // Assert
        static::assertSame([], $failures);
    }

    public function testTheFeedNeverAdvertisesAForeignHost(): void
    {
        // Arrange
        $client = static::createClient();
        $host = $this->configuredHost();

        // Act
        $foreign = array_values(array_filter(
            $this->feedLocs($client, $host),
            static fn(string $loc): bool => parse_url($loc, PHP_URL_HOST) !== $host,
        ));

        // Assert
        static::assertSame([], $foreign);
    }

    private function configuredHost(): string
    {
        return (string) parse_url(static::getContainer()->get(ConfigService::class)->getHost(), PHP_URL_HOST);
    }

    /**
     * @return list<string>
     */
    private function feedLocs(KernelBrowser $client, string $host): array
    {
        $client->request('GET', '/sitemap.xml', server: ['HTTP_HOST' => $host]);
        static::assertResponseIsSuccessful();

        preg_match_all('#<loc>([^<]*)</loc>#', (string) $client->getResponse()->getContent(), $matches);

        return array_map(htmlspecialchars_decode(...), $matches[1]);
    }

    /**
     * @param list<string> $locs
     * @return list<string>
     */
    private function sample(array $locs): array
    {
        if (count($locs) <= self::MAX_URLS_WALKED) {
            return $locs;
        }

        $stride = (int) ceil(count($locs) / self::MAX_URLS_WALKED);

        return array_values(array_filter($locs, static fn(int $i): bool => $i % $stride === 0, ARRAY_FILTER_USE_KEY));
    }

    private function statusOf(KernelBrowser $client, string $host, string $loc): int
    {
        $client->request('GET', $this->pathOf($loc), server: ['HTTP_HOST' => $host]);

        return $client->getResponse()->getStatusCode();
    }

    private function canonicalOf(KernelBrowser $client, string $host, string $loc): ?string
    {
        $crawler = $client->request('GET', $this->pathOf($loc), server: ['HTTP_HOST' => $host]);
        $tags = $crawler->filter('link[rel="canonical"]');

        return $tags->count() === 0 ? null : (string) $tags->first()->attr('href');
    }

    private function pathOf(string $url): string
    {
        return (string) (parse_url($url, PHP_URL_PATH) ?? '/');
    }

    private function identity(string $url): string
    {
        return sprintf('%s%s', parse_url($url, PHP_URL_HOST), $this->pathOf($url));
    }
}
