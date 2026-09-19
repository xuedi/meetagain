<?php declare(strict_types=1);

namespace App\Enum;

enum SecurityRecommendation: string
{
    case Handled = 'handled';
    case Block = 'block';
    case BlockSession = 'block_session';
    case BlockShortCircuit = 'block_short_circuit';

    public function isBlocking(): bool
    {
        return $this !== self::Handled;
    }

    public function blocksIp(): bool
    {
        return $this === self::Block || $this === self::BlockShortCircuit;
    }
}
