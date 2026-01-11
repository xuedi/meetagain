<?php declare(strict_types=1);

namespace Plugin\Bookclub\Entity;

enum SuggestionStatus: int
{
    case Pending = 0;
    case InPoll = 1;
    case Selected = 2;
    case Rejected = 3;
    case Withdrawn = 4;
}
