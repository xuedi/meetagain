<?php declare(strict_types=1);

namespace Plugin\Ranking\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Plugin\Ranking\Entity\MemberRank;
use Plugin\Ranking\Entity\RankDefinition;
use Plugin\Ranking\Entity\RankingConfig;
use Plugin\Ranking\Enum\Archetype;
use Plugin\Ranking\Repository\RankDefinitionRepository;
use Plugin\Ranking\Service\LeaderboardOrderService;
use ReflectionProperty;

final class LeaderboardOrderServiceTest extends TestCase
{
    public function testNumericArchetypeSortsDescending(): void
    {
        // Arrange
        $config = new RankingConfig();
        $config->setGroupId(1);
        $config->setArchetype(Archetype::Elo);

        $low = $this->makeRank(numericValue: 1200);
        $high = $this->makeRank(numericValue: 2100);
        $mid = $this->makeRank(numericValue: 1700);

        $service = new LeaderboardOrderService($this->createStub(RankDefinitionRepository::class));

        // Act
        $sorted = $service->sort($config, [$low, $high, $mid]);

        // Assert
        static::assertSame([$high, $mid, $low], $sorted);
    }

    public function testTieredArchetypeSortsByPositionDescending(): void
    {
        // Arrange
        $config = new RankingConfig();
        $config->setGroupId(1);
        $config->setArchetype(Archetype::Belt);

        $defA = $this->makeDefinition(1, 0);
        $defB = $this->makeDefinition(2, 3);
        $defC = $this->makeDefinition(3, 1);

        $repo = $this->createStub(RankDefinitionRepository::class);
        $repo->method('findByConfig')->willReturn([$defA, $defB, $defC]);

        $rankA = $this->makeRank(definitionId: 1);
        $rankB = $this->makeRank(definitionId: 2);
        $rankC = $this->makeRank(definitionId: 3);

        $service = new LeaderboardOrderService($repo);

        // Act
        $sorted = $service->sort($config, [$rankA, $rankB, $rankC]);

        // Assert
        static::assertSame([$rankB, $rankC, $rankA], $sorted);
    }

    public function testOrphanedRanksGoLast(): void
    {
        // Arrange
        $config = new RankingConfig();
        $config->setGroupId(1);
        $config->setArchetype(Archetype::Belt);

        $defA = $this->makeDefinition(1, 5);
        $repo = $this->createStub(RankDefinitionRepository::class);
        $repo->method('findByConfig')->willReturn([$defA]);

        $known = $this->makeRank(definitionId: 1);
        $orphan = $this->makeRank(definitionId: 999);

        $service = new LeaderboardOrderService($repo);

        // Act
        $sorted = $service->sort($config, [$orphan, $known]);

        // Assert
        static::assertSame([$known, $orphan], $sorted);
    }

    private function makeRank(?int $numericValue = null, ?int $definitionId = null): MemberRank
    {
        $rank = new MemberRank();
        $rank->setUserId(1);
        $rank->setGroupId(1);
        $rank->setNumericValue($numericValue);
        $rank->setRankDefinitionId($definitionId);

        return $rank;
    }

    private function makeDefinition(int $id, int $position): RankDefinition
    {
        $def = new RankDefinition();
        $def->setLabel('x');
        $def->setPosition($position);
        $idProp = new ReflectionProperty(RankDefinition::class, 'id');
        $idProp->setValue($def, $id);

        return $def;
    }
}
