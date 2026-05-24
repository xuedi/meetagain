<?php declare(strict_types=1);

namespace Plugin\Ranking\Service;

use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Plugin\Ranking\Entity\RankDefinition;
use Plugin\Ranking\Entity\RankingConfig;
use Plugin\Ranking\Repository\RankDefinitionRepository;
use Plugin\Ranking\ValueObject\ArchetypePresets;
use Plugin\Ranking\ValueObject\RankPreset;

readonly class RankDefinitionService
{
    public function __construct(
        private RankDefinitionRepository $repository,
        private EntityManagerInterface $entityManager,
    ) {}

    public function create(RankingConfig $config, string $label, ?string $colorHex, int $position): RankDefinition
    {
        $definition = new RankDefinition();
        $definition->setConfig($config);
        $definition->setLabel($label);
        $definition->setColorHex($this->normaliseColorHex($colorHex));
        $definition->setPosition($position);
        $this->repository->save($definition, true);

        return $definition;
    }

    public function update(RankDefinition $definition, string $label, ?string $colorHex, int $position): void
    {
        $definition->setLabel($label);
        $definition->setColorHex($this->normaliseColorHex($colorHex));
        $definition->setPosition($position);
        $this->repository->save($definition, true);
    }

    public function delete(RankDefinition $definition): void
    {
        $this->repository->remove($definition, true);
    }

    /**
     * Replaces all definitions for a config with the chosen preset's entries in one transaction.
     * Existing MemberRank rows are intentionally not touched - they point at the now-deleted
     * definition by FK-less ID and will show "rank no longer defined" until the member re-picks.
     */
    public function applyPreset(RankingConfig $config, RankPreset $preset): void
    {
        if ($preset->archetype !== $config->getArchetype()) {
            throw new InvalidArgumentException(sprintf(
                'Preset "%s" is for archetype "%s" but config uses "%s".',
                $preset->key,
                $preset->archetype->value,
                $config->getArchetype()->value,
            ));
        }

        $this->entityManager->wrapInTransaction(function () use ($config, $preset): void {
            $this->repository->deleteAllForConfig($config);
            $this->entityManager->flush();

            $position = 0;
            foreach ($preset->entries as $entry) {
                $definition = new RankDefinition();
                $definition->setConfig($config);
                $definition->setLabel($entry->label);
                $definition->setColorHex($this->normaliseColorHex($entry->colorHex));
                $definition->setLabelKey($entry->labelKey);
                $definition->setPosition($position++);
                $this->repository->save($definition);
            }
            $this->entityManager->flush();
        });
    }

    /**
     * @return list<RankPreset>
     */
    public function availablePresets(RankingConfig $config): array
    {
        return ArchetypePresets::forArchetype($config->getArchetype());
    }

    private function normaliseColorHex(?string $colorHex): ?string
    {
        if ($colorHex === null || $colorHex === '') {
            return null;
        }
        $hex = strtolower($colorHex);
        if (!str_starts_with($hex, '#')) {
            $hex = '#' . $hex;
        }
        if (!preg_match('/^#[0-9a-f]{6}$/', $hex)) {
            throw new InvalidArgumentException(sprintf('Invalid hex color: %s', $colorHex));
        }

        return $hex;
    }
}
