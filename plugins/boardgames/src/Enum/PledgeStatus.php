<?php declare(strict_types=1);

namespace Plugin\Boardgames\Enum;

enum PledgeStatus: string
{
    case Pledged = 'pledged';
    case Withdrawn = 'withdrawn';
}
