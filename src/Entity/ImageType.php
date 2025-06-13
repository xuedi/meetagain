<?php declare(strict_types=1);

namespace App\Entity;

enum ImageType: int
{
    case ProfilePicture = 1;
    case EventTeaser = 2;
    case EventUpload = 3;
    case CmsBlock = 4;
}
