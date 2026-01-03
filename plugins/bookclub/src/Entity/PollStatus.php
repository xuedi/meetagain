<?php declare(strict_types=1);

namespace Plugin\Bookclub\Entity;

enum PollStatus: int
{
    case Draft = 0;
    case Active = 1;
    case Closed = 2;
}
