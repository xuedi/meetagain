<?php declare(strict_types=1);

namespace App\Entity\BlockType;

use App\Entity\CmsBlockTypes;

class Title implements BlockType
{
    private function __construct(public string $title) {
    }

    public static function fromJson(array $json): self
    {
        return new self(
            $json['title'],
        );
    }

    public static function getType(): CmsBlockTypes
    {
        return CmsBlockTypes::Title;
    }

    public function toArray(): array
    {
        return [
            'title' => $this->title,
        ];
    }
}
