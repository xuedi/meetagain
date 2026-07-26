<?php declare(strict_types=1);

namespace Plugin\Voting\Enum;

enum ChoiceMode: string
{
    case Multiple = 'multiple';
    case Single = 'single';
}
