<?php declare(strict_types=1);

namespace Plugin\Dinnerclub\Enum;

enum DishImageSuggestionType: string
{
    case AddImage = 'add_image';
    case SetPreview = 'set_preview';
}
