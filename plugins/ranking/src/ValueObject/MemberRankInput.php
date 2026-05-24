<?php declare(strict_types=1);

namespace Plugin\Ranking\ValueObject;

use Plugin\Ranking\Entity\RankDefinition;

final class MemberRankInput
{
    public ?int $numericValue = null;
    public ?RankDefinition $definition = null;
}
