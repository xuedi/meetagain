<?php declare(strict_types=1);

namespace Plugin\Boardgames\Service;

use Plugin\Boardgames\Enum\ExternalSource;

readonly class GameMetadata
{
    public function __construct(
        public string $externalId,
        public ExternalSource $source,
        public string $name,
        public ?string $originalName = null,
        public ?string $description = null,
        public ?int $yearPublished = null,
        public ?int $minPlayers = null,
        public ?int $maxPlayers = null,
        public ?int $bestPlayerCount = null,
        public ?int $minPlaytime = null,
        public ?int $maxPlaytime = null,
        public ?int $minAge = null,
        public ?string $weight = null,
        public ?string $boxImageUrl = null,
        public ?string $bggId = null,
    ) {}

    public function getPlayerRange(): ?string
    {
        if ($this->minPlayers === null && $this->maxPlayers === null) {
            return null;
        }

        if ($this->minPlayers !== null && $this->maxPlayers !== null && $this->minPlayers !== $this->maxPlayers) {
            return $this->minPlayers . '-' . $this->maxPlayers;
        }

        return (string) ($this->minPlayers ?? $this->maxPlayers);
    }
}
