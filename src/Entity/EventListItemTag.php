<?php

declare(strict_types=1);

namespace App\Entity;

readonly class EventListItemTag
{
    public function __construct(
        public string $text,
        public string $color = 'is-light',
        public ?string $icon = null,
        public ?string $url = null,
    ) {}
}
