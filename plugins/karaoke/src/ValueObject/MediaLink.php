<?php declare(strict_types=1);

namespace Plugin\Karaoke\ValueObject;

use Plugin\Karaoke\Enum\MediaProvider;

final readonly class MediaLink
{
    public function __construct(
        public MediaProvider $provider,
        public string $id,
    ) {}

    public function getEmbedUrl(): string
    {
        return $this->provider->getEmbedUrl($this->id);
    }

    public function getWatchUrl(): string
    {
        return $this->provider->getWatchUrl($this->id);
    }
}
