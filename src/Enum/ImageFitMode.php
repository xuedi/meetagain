<?php declare(strict_types=1);

namespace App\Enum;

enum ImageFitMode: string
{
    case Crop = 'crop';
    case Fit = 'fit';
}
