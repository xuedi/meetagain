<?php declare(strict_types=1);

namespace App\Entity\BlockType;

use App\Entity\CmsBlockTypes;

class Headline implements BlockType
{
    private function __construct(public string $title)
    {
    }

    #[\Override]
    public static function fromJson(array $json): self
    {
        return new self($json['title']);
    }

    #[\Override]
    public static function getType(): CmsBlockTypes
    {
        return CmsBlockTypes::Headline;
    }

    #[\Override]
    public function toArray(): array
    {
        return [
            'title' => $this->title,
        ];
    }
}
