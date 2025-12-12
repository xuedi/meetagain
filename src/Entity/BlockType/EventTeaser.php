<?php declare(strict_types=1);

namespace App\Entity\BlockType;

use App\Entity\CmsBlockTypes;
use App\Entity\Image as ImageEntity;

class EventTeaser implements BlockType
{
    private function __construct(
        public string $headline,
        public string $text,
        public null|ImageEntity $image,
    ) {
    }

    #[\Override]
    public static function fromJson(array $json, null|ImageEntity $image = null): self
    {
        return new self($json['headline'], $json['text'], $image);
    }

    #[\Override]
    public static function getType(): CmsBlockTypes
    {
        return CmsBlockTypes::EventTeaser;
    }

    #[\Override]
    public function toArray(): array
    {
        return [
            'headline' => $this->headline,
            'text' => $this->text,
            'image' => $this->image,
        ];
    }
}
