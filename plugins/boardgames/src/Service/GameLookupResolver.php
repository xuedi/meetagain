<?php declare(strict_types=1);

namespace Plugin\Boardgames\Service;

use App\Service\Security\SecretBox;
use Plugin\Boardgames\Enum\ExternalSource;
use Plugin\Boardgames\ValueObject\Config;
use Psr\Log\LoggerInterface;
use SensitiveParameter;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

readonly class GameLookupResolver
{
    public function __construct(
        private ConfigService $configService,
        private SecretBox $secretBox,
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        #[Autowire('%env(default::BGG_API_TOKEN)%')]
        #[SensitiveParameter]
        private ?string $bggApiToken = null,
    ) {}

    public function resolve(): ?GameMetadataLookupInterface
    {
        $config = $this->configService->getConfig();

        return match ($config->getAdapter() ?? ($this->environmentToken() === null ? null : ExternalSource::Bgg)) {
            ExternalSource::Bgg => $this->createBgg($config),
            ExternalSource::Wikidata => new WikidataLookup($this->httpClient, $this->logger),
            default => null,
        };
    }

    private function createBgg(Config $config): ?GameMetadataLookupInterface
    {
        $encrypted = $config->getEncryptedBggToken();
        $token = $encrypted === null ? $this->environmentToken() : $this->secretBox->decrypt($encrypted);
        if ($token === null) {
            return null;
        }

        return new BggLookup($this->httpClient, $this->logger, $token);
    }

    private function environmentToken(): ?string
    {
        return $this->bggApiToken === null || $this->bggApiToken === '' ? null : $this->bggApiToken;
    }
}
