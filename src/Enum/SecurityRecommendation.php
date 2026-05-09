<?php declare(strict_types=1);

namespace App\Enum;

enum SecurityRecommendation: string
{
    case Handled = 'handled';
    case Block = 'block';
    case BlockShortCircuit = 'block_short_circuit';
}
