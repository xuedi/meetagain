<?php declare(strict_types=1);

namespace App\Enum\CmsBlock;

enum FieldType: string
{
    case String    = 'string';
    case Text      = 'text';
    case Boolean   = 'boolean';
    case Color     = 'color';
    case ImageList = 'imageList';
}
