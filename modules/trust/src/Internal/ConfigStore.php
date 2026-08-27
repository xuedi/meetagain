<?php declare(strict_types=1);

namespace Module\Trust\Internal;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Module\Trust\Contract\TrustConfig;
use Module\Trust\Internal\Entity\TrustContextConfig;
use Module\Trust\Internal\Repository\TrustContextConfigRepository;
use Override;
use Symfony\Contracts\Service\ResetInterface;

final class ConfigStore implements ResetInterface
{
    /** @var array<string, TrustConfig> */
    private array $memo = [];

    public function __construct(
        private readonly TrustContextConfigRepository $repository,
        private readonly EntityManagerInterface $entityManager,
    ) {}

    public function get(string $context): TrustConfig
    {
        return $this->memo[$context] ??= $this->hydrate($this->repository->findByContext($context));
    }

    public function save(string $context, TrustConfig $config): void
    {
        $now = new DateTimeImmutable('now');
        $entity = $this->repository->findByContext($context);

        if ($entity === null) {
            $entity = new TrustContextConfig($context, $config->toArray(), $now);
            $this->entityManager->persist($entity);
        } else {
            $entity->setPayload($config->toArray(), $now);
        }

        $this->entityManager->flush();
        unset($this->memo[$context]);
    }

    public function getRevision(string $context): ?string
    {
        return $this->repository->findByContext($context)?->getUpdatedAt()->format('U.u');
    }

    public function isConfigured(string $context): bool
    {
        return $this->repository->findByContext($context) !== null;
    }

    #[Override]
    public function reset(): void
    {
        $this->memo = [];
    }

    private function hydrate(?TrustContextConfig $entity): TrustConfig
    {
        if ($entity === null) {
            return new TrustConfig();
        }

        $payload = $entity->getPayload();
        $defaults = new TrustConfig();

        return new TrustConfig(
            maxScore: $this->readInt($payload, 'maxScore', $defaults->maxScore),
            percentSlight: $this->readInt($payload, 'percentSlight', $defaults->percentSlight),
            percentTrusted: $this->readInt($payload, 'percentTrusted', $defaults->percentTrusted),
            percentAbsolute: $this->readInt($payload, 'percentAbsolute', $defaults->percentAbsolute),
            rootPointsPrimary: $this->readInt($payload, 'rootPointsPrimary', $defaults->rootPointsPrimary),
            rootPointsSecondary: $this->readInt($payload, 'rootPointsSecondary', $defaults->rootPointsSecondary),
            pointsPerAction: $this->readIntMap($payload, 'pointsPerAction'),
            capsPerAction: $this->readIntMap($payload, 'capsPerAction'),
            minimumToParticipate: $this->readInt($payload, 'minimumToParticipate', $defaults->minimumToParticipate),
            bandThresholds: $this->readThresholds($payload, $defaults->bandThresholds),
        );
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, int>
     */
    private function readIntMap(array $payload, string $key): array
    {
        $raw = $payload[$key] ?? null;
        if (!is_array($raw)) {
            return [];
        }

        $map = [];
        foreach ($raw as $action => $value) {
            if (!is_string($action) || $action === '' || !is_numeric($value)) {
                continue;
            }
            $map[$action] = (int) $value;
        }

        return $map;
    }

    /**
     * @param array<string, mixed> $payload
     * @param array{int, int, int} $fallback
     * @return array{int, int, int}
     */
    private function readThresholds(array $payload, array $fallback): array
    {
        $raw = $payload['bandThresholds'] ?? null;
        if (!is_array($raw)) {
            return $fallback;
        }

        $values = array_values(array_map(static fn(mixed $value): int => (int) $value, $raw));
        if (count($values) !== 3) {
            return $fallback;
        }

        return [$values[0], $values[1], $values[2]];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function readInt(array $payload, string $key, int $fallback): int
    {
        $value = $payload[$key] ?? null;

        return is_int($value) ? $value : $fallback;
    }
}
