<?php declare(strict_types=1);

// Minimal stubs for the PECL `imagick` extension.
// Only the API surface actually used in this codebase is declared.
// If you use a new Imagick method or class, add its signature here.

class ImagickPixel
{
    public function __construct(?string $color = null) {}
}

class ImagickDraw
{
    public function setFillColor(ImagickPixel|string $color): bool
    {
        return true;
    }
    public function setFont(string $font): bool
    {
        return true;
    }
    public function setFontSize(float $size): bool
    {
        return true;
    }
    public function setGravity(int $gravity): bool
    {
        return true;
    }
    public function rectangle(float $x1, float $y1, float $x2, float $y2): bool
    {
        return true;
    }
    public function line(float $sx, float $sy, float $ex, float $ey): bool
    {
        return true;
    }
}

class Imagick
{
    public function __construct(?string $files = null) {}

    public function newImage(int $columns, int $rows, ImagickPixel|string $background, string $format = ''): bool
    {
        return true;
    }
    public function setImageFormat(string $format): bool
    {
        return true;
    }
    public function drawImage(ImagickDraw $draw): bool
    {
        return true;
    }
    public function annotateImage(ImagickDraw $draw, float $x, float $y, float $angle, string $text): bool
    {
        return true;
    }
    public function rotateImage(ImagickPixel|string $background, float $degrees): bool
    {
        return true;
    }
    public function resizeImage(int $columns, int $rows, int $filter, float $blur, bool $bestfit = false): bool
    {
        return true;
    }
    public function scaleImage(int $columns, int $rows, bool $bestfit = false): bool
    {
        return true;
    }
    public function thumbnailImage(int $columns, int $rows, bool $bestfit = false, bool $fill = false): bool
    {
        return true;
    }
    public function cropImage(int $width, int $height, int $x, int $y): bool
    {
        return true;
    }
    public function setImageCompression(int $compression): bool
    {
        return true;
    }
    public function setImageCompressionQuality(int $quality): bool
    {
        return true;
    }
    public function readImageBlob(string $image, ?string $filename = null): bool
    {
        return true;
    }
    public function writeImage(?string $filename = null): bool
    {
        return true;
    }
    public function getImageBlob(): string
    {
        return '';
    }
    public function getImageWidth(): int
    {
        return 0;
    }
    public function getImageHeight(): int
    {
        return 0;
    }
    public function stripImage(): bool
    {
        return true;
    }
    public function clear(): bool
    {
        return true;
    }
    public function destroy(): bool
    {
        return true;
    }
}

class ImagickException extends \Exception {}
