<?php declare(strict_types=1);

namespace Module\Email\Contract;

enum GuardOutcome: string
{
    case Pass = 'pass';
    case Skip = 'skip';
    case Error = 'error';
}
