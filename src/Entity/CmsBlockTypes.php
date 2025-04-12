<?php declare(strict_types=1);

namespace App\Entity;

enum CmsBlockTypes: int
{
    case Headline = 1;
    case Text = 2;
    case Image = 3;
    case Video = 4;
    case Paragraph = 5;
    case Events = 6;
    case Gallery = 7;
    case Hero = 8;
    case EventTeaser = 9;
}
