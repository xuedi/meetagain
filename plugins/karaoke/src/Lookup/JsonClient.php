<?php declare(strict_types=1);

namespace Plugin\Karaoke\Lookup;

use App\Service\Config\ConfigService;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

readonly class JsonClient
{
    public function __construct(
        #[Autowire(service: 'karaoke.client')]
        private HttpClientInterface $httpClient,
        private ConfigService $config,
    ) {}

    /**
     * @param array<string, scalar> $query
     *
     * @return array<array-key, mixed>|null null when the service answers 404
     */
    public function get(string $url, array $query = []): ?array
    {
        $response = $this->httpClient->request('GET', $url, [
            'query' => $query,
            'headers' => [
                'User-Agent' => sprintf('%s (+%s) MeetAgain-Karaoke', $this->config->getSiteName(), $this->config->getHost()),
                'Accept' => 'application/json',
            ],
        ]);

        if ($response->getStatusCode() === 404) {
            return null;
        }

        return $response->toArray();
    }
}
