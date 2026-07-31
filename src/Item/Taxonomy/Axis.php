<?php declare(strict_types=1);

namespace App\Item\Taxonomy;

enum Axis: string
{
    case Category = 'category';
    case Tag = 'tag';
}
