<?php declare(strict_types=1);

namespace App\Entity\BlockType;

use App\Entity\CmsBlockTypes;
use App\Entity\Image as ImageEntity;

class Text implements BlockType
{
    private function __construct(public string $content, public ?ImageEntity $image)
    {
    }

    #[\Override]
    public static function fromJson(array $json, ?ImageEntity $image = null): self
    {
        return new self($json['content'], $image);
    }

    #[\Override]
    public static function getType(): CmsBlockTypes
    {
        return CmsBlockTypes::Text;
    }

    #[\Override]
    public function toArray(): array
    {
        return [
            'content' => $this->content,
            'image' => $this->image,
        ];
    }
}
