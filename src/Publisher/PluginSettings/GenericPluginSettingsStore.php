<?php declare(strict_types=1);

namespace App\Publisher\PluginSettings;

use App\Entity\PluginSettings;
use App\Repository\PluginSettingsRepository;
use App\Service\Admin\PluginSettingsService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;

/**
 * Global-scope store backed by the single plugin_settings JSON table. An adopting plugin
 * whose DTO implements PluginSettingsData needs no entity or migration of its own. Lowest
 * priority, so any custom store outranks it. Handles only the global scope; override
 * scopes are owned by whoever supplies the scope provider and its store.
 */
readonly class GenericPluginSettingsStore implements PluginSettingsStoreInterface
{
    public function __construct(
        private PluginSettingsRepository $repository,
        private EntityManagerInterface $em,
        private PluginSettingsService $descriptors,
    ) {}

    public function supports(string $key, ?string $scopeId): bool
    {
        return $scopeId === null;
    }

    public function load(string $key, ?string $scopeId): ?object
    {
        $row = $this->repository->findOneByPluginKey($key);
        if ($row === null) {
            return null;
        }

        return $this->dataClass($key)::fromArray($row->getData());
    }

    public function save(string $key, object $data, ?string $scopeId): void
    {
        if (!$data instanceof PluginSettingsData) {
            throw new LogicException(sprintf('Data for "%s" must implement %s to use the generic store.', $key, PluginSettingsData::class));
        }

        $row = $this->repository->findOneByPluginKey($key) ?? new PluginSettings();
        $row->setPluginKey($key);
        $row->setData($data->toArray());
        $row->setUpdatedAt(new DateTimeImmutable());

        $this->em->persist($row);
        $this->em->flush();
    }

    public function getPriority(): int
    {
        return -100;
    }

    /** @return class-string<PluginSettingsData> */
    private function dataClass(string $key): string
    {
        $default = $this->descriptors->getProvider($key)?->createDefault();
        if (!$default instanceof PluginSettingsData) {
            throw new LogicException(sprintf('Descriptor for "%s" must create a %s to use the generic store.', $key, PluginSettingsData::class));
        }

        return $default::class;
    }
}
