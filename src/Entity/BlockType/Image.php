<?php declare(strict_types=1);

namespace App\Entity\BlockType;

use App\Entity\CmsBlockTypes;

class Image implements BlockType
{
    private function __construct(public string $id)
    {
    }

    #[\Override]
    public static function fromJson(array $json): self
    {
        return new self($json['id']);
    }

    #[\Override]
    public static function getType(): CmsBlockTypes
    {
        return CmsBlockTypes::Image;
    }

    #[\Override]
    public function toArray(): array
    {
        return [
            'id' => $this->id,
        ];
    }
}
