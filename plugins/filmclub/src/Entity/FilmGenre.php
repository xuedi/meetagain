<?php declare(strict_types=1);

namespace Plugin\Filmclub\Entity;

enum FilmGenre: string
{
    case Action = 'action';
    case Comedy = 'comedy';
    case Drama = 'drama';
    case Horror = 'horror';
    case SciFi = 'scifi';
    case Thriller = 'thriller';
    case Documentary = 'documentary';
    case Animation = 'animation';
    case Fantasy = 'fantasy';
    case Mystery = 'mystery';
    case Romance = 'romance';
    case Western = 'western';
}
