<?php declare(strict_types=1);

namespace App\Service\Media;

use Imagick;
use ImagickException;
use RuntimeException;

readonly class MetadataStripper
{
    private const string ICC = 'icc';
    private const array LOSSY_FORMATS = ['HEIC', 'AVIF', 'WEBP'];
    private const int DEFAULT_QUALITY = 90;

    public function strip(string $content): string
    {
        $imagick = $this->decode($content);
        try {
            if (!$this->needsStripping($imagick)) {
                return $content;
            }

            $icc = $imagick->getImageProfiles(self::ICC, true)[self::ICC] ?? null;
            $imagick->autoOrient();
            $imagick->stripImage();
            if (is_string($icc)) {
                $imagick->setImageProfile(self::ICC, $icc);
            }
            $isLossy = in_array(strtoupper($imagick->getImageFormat()), self::LOSSY_FORMATS, true);
            if ($isLossy && $imagick->getImageCompressionQuality() === 0) {
                $imagick->setImageCompressionQuality(self::DEFAULT_QUALITY);
            }

            return $imagick->getImageBlob();
        } catch (ImagickException $e) {
            throw new RuntimeException('The image could not be rewritten without its metadata.', 0, $e);
        } finally {
            $imagick->clear();
        }
    }

    private function decode(string $content): Imagick
    {
        try {
            $imagick = new Imagick();
            $imagick->readImageBlob($content);
        } catch (ImagickException $e) {
            throw new RuntimeException('The file is not an image that can be read.', 0, $e);
        }

        return $imagick;
    }

    private function needsStripping(Imagick $imagick): bool
    {
        if ($imagick->getNumberImages() > 1) {
            return false;
        }

        $orientation = $imagick->getImageOrientation();
        $isUpright = $orientation === Imagick::ORIENTATION_UNDEFINED || $orientation === Imagick::ORIENTATION_TOPLEFT;

        return !$isUpright || array_diff($imagick->getImageProfiles('*', false), [self::ICC]) !== [];
    }
}
