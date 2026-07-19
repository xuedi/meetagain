<?php declare(strict_types=1);

namespace Plugin\Voting\Enum;

/**
 * How many candidates a voter may back on one poll. Multiple is approval voting (tick any
 * number); Single restricts each voter to one candidate. Multiple is the neutral default.
 */
enum ChoiceMode: string
{
    case Multiple = 'multiple';
    case Single = 'single';
}
