<?php declare(strict_types=1);

namespace App\Portability;

use App\Entity\Image;

/**
 * Writes an image into the archive being assembled and returns the archive-relative path to store
 * alongside the exported row, or null when the image file is missing.
 */
interface ImageWriterInterface
{
    public function addImage(Image $image): ?string;
}
