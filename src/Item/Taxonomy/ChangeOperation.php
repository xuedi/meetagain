<?php declare(strict_types=1);

namespace App\Item\Taxonomy;

enum ChangeOperation: string
{
    case Add = 'add';
    case Rename = 'rename';
    case Remove = 'remove';
}
