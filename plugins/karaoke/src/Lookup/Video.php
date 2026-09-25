<?php declare(strict_types=1);

namespace Plugin\Karaoke\Lookup;

final readonly class Video
{
    public function __construct(
        public string $title,
        public ?string $author = null,
        public ?int $durationSeconds = null,
    ) {}
}
