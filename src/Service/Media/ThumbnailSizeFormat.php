<?php declare(strict_types=1);

namespace App\Service\Media;

use App\Service\Media\ImageTypes\ImageTypeDefinitionInterface;

readonly class ThumbnailSizeFormat
{
    public function format(int $width, int $height): string
    {
        if ($width === ImageTypeDefinitionInterface::FREE_AXIS) {
            return sprintf('h%d', $height);
        }

        if ($height === ImageTypeDefinitionInterface::FREE_AXIS) {
            return sprintf('w%d', $width);
        }

        return sprintf('%dx%d', $width, $height);
    }

    /**
     * @return array{0: int, 1: int}|null null when the token is not a thumbnail size
     */
    public function parse(string $size): ?array
    {
        $matches = [];

        if (preg_match('/^h(\d+)$/', $size, $matches) === 1) {
            return [ImageTypeDefinitionInterface::FREE_AXIS, (int) $matches[1]];
        }

        if (preg_match('/^w(\d+)$/', $size, $matches) === 1) {
            return [(int) $matches[1], ImageTypeDefinitionInterface::FREE_AXIS];
        }

        if (preg_match('/^(\d+)x(\d+)$/', $size, $matches) === 1) {
            return [(int) $matches[1], (int) $matches[2]];
        }

        return null;
    }
}
