<?php declare(strict_types=1);

namespace Module\Trust\Tests\Stub;

use Doctrine\ORM\EntityManagerInterface;
use Module\Trust\Contract\ActionSourceInterface;
use Module\Trust\Contract\ContextDescriberInterface;
use Module\Trust\Internal\ActionRegistry;
use Module\Trust\Internal\ConfigStore;
use Module\Trust\Internal\ContextRegistry;
use Module\Trust\Internal\Repository\TrustContextConfigRepository;
use Module\Trust\Internal\Repository\TrustGrantRepository;
use Module\Trust\Internal\ScoreCalculator;
use Module\Trust\Internal\ScoreProvider;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

trait ScoreProviders
{
    private function configStore(): ConfigStore
    {
        $configRepository = $this->createStub(TrustContextConfigRepository::class);
        $configRepository->method('findByContext')->willReturn(null);

        return new ConfigStore($configRepository, $this->createStub(EntityManagerInterface::class));
    }

    private function grantsWithoutEdges(): TrustGrantRepository
    {
        $grants = $this->createStub(TrustGrantRepository::class);
        $grants->method('findEdges')->willReturn([]);
        $grants->method('findRevision')->willReturn(null);
        $grants->method('countIncomingByUser')->willReturn([]);
        $grants->method('findOutgoing')->willReturn([]);

        return $grants;
    }

    /** @param list<ActionSourceInterface> $sources */
    private function scoreProvider(
        array $sources,
        ?TrustGrantRepository $grants = null,
        ?CacheItemPoolInterface $cache = null,
        ?ConfigStore $configStore = null,
        ?ContextDescriberInterface $describer = null,
    ): ScoreProvider {
        return new ScoreProvider(
            $sources,
            [],
            new ContextRegistry([$describer ?? new ContextDescriber()]),
            new ActionRegistry($sources),
            $configStore ?? $this->configStore(),
            new ScoreCalculator(new NullLogger()),
            $grants ?? $this->grantsWithoutEdges(),
            $cache ?? new ArrayAdapter(),
            new NullLogger(),
        );
    }
}
