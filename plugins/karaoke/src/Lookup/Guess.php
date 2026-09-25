<?php declare(strict_types=1);

namespace Plugin\Karaoke\Lookup;

final readonly class Guess
{
    /**
     * @param list<string> $titles
     */
    public function __construct(
        public array $titles,
        public ?string $artist = null,
        public ?string $language = null,
    ) {}

    public function getTitle(): ?string
    {
        return $this->titles[0] ?? null;
    }
}
