<?php declare(strict_types=1);

namespace App\Item\Portability;

use App\Entity\Image;

/**
 * Writes an image into the archive being assembled and returns the archive-relative path to store
 * alongside the exported row, or null when the image file is missing. Core defines the contract and
 * ships no implementation; the component assembling the archive provides one.
 */
interface PortableImageWriterInterface
{
    public function addImage(Image $image, string $nameHint): ?string;
}
