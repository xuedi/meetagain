<?php declare(strict_types=1);

namespace Plugin\Dishes\Entity;

enum DishSuggestionField: string
{
    case Name = 'name';
    case Phonetic = 'phonetic';
    case Description = 'description';
    case Recipe = 'recipe';
    case Origin = 'origin';
}
