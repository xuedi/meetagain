<?php declare(strict_types=1);

namespace App\Entity\BlockType;

use App\Entity\CmsBlockTypes;

class Text implements BlockType
{
    private function __construct(public string $content)
    {
    }

    #[\Override]
    public static function fromJson(array $json): self
    {
        return new self($json['content']);
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
        ];
    }
}
