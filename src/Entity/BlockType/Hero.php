<?php declare(strict_types=1);

namespace App\Entity\BlockType;

use App\Entity\CmsBlockTypes;
use App\Entity\Image as ImageEntity;
use Override;

class Hero implements BlockType
{
    private function __construct(
        public string $headline,
        public string $subHeadline,
        public string $text,
        public string $buttonLink,
        public string $buttonText,
        public string $color,
        public ?ImageEntity $image,
    ) {
    }

    #[Override]
    public static function fromJson(array $json, ?ImageEntity $image = null): self
    {
        return new self(
            $json['headline'],
            $json['subHeadline'],
            $json['text'],
            $json['buttonLink'],
            $json['buttonText'],
            $json['color'] ?? '#f14668',
            $image,
        );
    }

    #[Override]
    public static function getType(): CmsBlockTypes
    {
        return CmsBlockTypes::Hero;
    }

    #[Override]
    public function toArray(): array
    {
        return [
            'headline' => $this->headline,
            'subHeadline' => $this->subHeadline,
            'text' => $this->text,
            'buttonLink' => $this->buttonLink,
            'buttonText' => $this->buttonText,
            'color' => $this->color,
            'image' => $this->image,
        ];
    }
}
