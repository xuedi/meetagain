<?php declare(strict_types=1);

namespace App\Entity\BlockType;

use App\Enum\ImageSupport;

readonly class BlockCapabilities
{
    public function __construct(
        public ImageSupport $image,
        public bool $supportsImageRight,
        public bool $isGallery,
    ) {}

    public function supportsImage(): bool
    {
        return $this->image !== ImageSupport::None;
    }

    public function requiresImage(): bool
    {
        return $this->image === ImageSupport::Required;
    }
}
