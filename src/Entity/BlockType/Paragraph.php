<?php declare(strict_types=1);

namespace App\Entity\BlockType;

use App\Entity\CmsBlockTypes;
use App\Entity\Image as ImageEntity;
use Override;

class Paragraph implements BlockType
{
    private function __construct(
        public string $title,
        public string $content,
        public ?ImageEntity $image,
    ) {
    }

    #[Override]
    public static function fromJson(array $json, ?ImageEntity $image = null): self
    {
        return new self($json['title'], $json['content'], $image);
    }

    #[Override]
    public static function getType(): CmsBlockTypes
    {
        return CmsBlockTypes::Paragraph;
    }

    #[Override]
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'content' => $this->content,
            'image' => $this->image,
        ];
    }
}
