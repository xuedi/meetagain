<?php declare(strict_types=1);

namespace Plugin\Voting\Entity;

enum PollStatus: int
{
    case Active = 1;
    case Closed = 2;
}
