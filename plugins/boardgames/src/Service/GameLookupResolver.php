<?php declare(strict_types=1);

namespace Plugin\Boardgames\Service;

use App\Service\Security\SecretBox;
use Plugin\Boardgames\Enum\ExternalSource;
use Plugin\Boardgames\ValueObject\Config;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

readonly class GameLookupResolver
{
    public function __construct(
        private ConfigService $configService,
        private SecretBox $secretBox,
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
    ) {}

    public function resolve(): ?GameMetadataLookupInterface
    {
        $config = $this->configService->getConfig();

        return match ($config->getAdapter()) {
            ExternalSource::Bgg => $this->createBgg($config),
            ExternalSource::Wikidata => new WikidataLookup($this->httpClient, $this->logger),
            default => null,
        };
    }

    private function createBgg(Config $config): ?GameMetadataLookupInterface
    {
        $encrypted = $config->getEncryptedBggToken();
        if ($encrypted === null) {
            return null;
        }

        return new BggLookup($this->httpClient, $this->logger, $this->secretBox->decrypt($encrypted));
    }
}
