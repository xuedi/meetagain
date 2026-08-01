<?php declare(strict_types=1);

namespace Plugin\Photos\Service;

use DateTimeImmutable;
use Imagick;
use Psr\Log\LoggerInterface;
use Throwable;

readonly class ExifService
{
    private const string EXIF_DATE_FORMAT = 'Y:m:d H:i:s';
    private const string META_DATE_FORMAT = 'Y-m-d H:i:s';

    public function __construct(
        private LoggerInterface $logger,
    ) {}

    /**
     * @return array<string, scalar>|null null when the file cannot be read or carries no EXIF block
     */
    public function extract(string $path): ?array
    {
        try {
            $imagick = new Imagick();
            $imagick->readImage($path);
            $properties = $imagick->getImageProperties('exif:*');
            $geometry = $imagick->getImageGeometry();
            $imagick->clear();
        } catch (Throwable $e) {
            $this->logger->warning('Could not read EXIF data from uploaded photo', ['path' => $path, 'error' => $e->getMessage()]);

            return null;
        }

        if ($properties === []) {
            return null;
        }

        $meta = [
            'make' => $this->text($properties['exif:Make'] ?? null),
            'model' => $this->text($properties['exif:Model'] ?? null),
            'lens' => $this->text($properties['exif:LensModel'] ?? null),
            'exposureTime' => $this->exposureTime($properties['exif:ExposureTime'] ?? null),
            'fNumber' => $this->decimal($properties['exif:FNumber'] ?? null, 1),
            'iso' => $this->integer($properties['exif:ISOSpeedRatings'] ?? $properties['exif:PhotographicSensitivity'] ?? null),
            'focalLength' => $this->decimal($properties['exif:FocalLength'] ?? null, 1),
            'focalLength35' => $this->integer($properties['exif:FocalLengthIn35mmFilm'] ?? null),
            'exposureBias' => $this->decimal($properties['exif:ExposureBiasValue'] ?? null, 2),
            'flash' => $this->flash($properties['exif:Flash'] ?? null),
            'whiteBalance' => $this->integer($properties['exif:WhiteBalance'] ?? null),
            'meteringMode' => $this->integer($properties['exif:MeteringMode'] ?? null),
            'takenAt' => $this->takenAt($properties['exif:DateTimeOriginal'] ?? null),
            'width' => $geometry['width'] ?? null,
            'height' => $geometry['height'] ?? null,
        ];

        $meta = array_filter($meta, static fn(mixed $value): bool => $value !== null);

        return $meta === [] ? null : $meta;
    }

    /** @param array<string, scalar>|null $meta */
    public function takenAtOf(?array $meta): ?DateTimeImmutable
    {
        $raw = $meta['takenAt'] ?? null;
        if (!is_string($raw)) {
            return null;
        }

        return DateTimeImmutable::createFromFormat(self::META_DATE_FORMAT, $raw) ?: null;
    }

    private function text(mixed $raw): ?string
    {
        if (!is_string($raw)) {
            return null;
        }

        $trimmed = trim($raw);

        return $trimmed === '' ? null : mb_substr($trimmed, 0, 255);
    }

    private function integer(mixed $raw): ?int
    {
        $value = $this->rational($raw);

        return $value === null ? null : (int) round($value);
    }

    private function decimal(mixed $raw, int $precision): ?float
    {
        $value = $this->rational($raw);

        return $value === null ? null : round($value, $precision);
    }

    private function flash(mixed $raw): ?bool
    {
        $value = $this->integer($raw);

        return $value === null ? null : ($value & 1) === 1;
    }

    private function exposureTime(mixed $raw): ?string
    {
        $seconds = $this->rational($raw);
        if ($seconds === null || $seconds <= 0.0) {
            return null;
        }

        if ($seconds >= 1.0) {
            return rtrim(rtrim(number_format($seconds, 1, '.', ''), '0'), '.');
        }

        return '1/' . (int) round(1 / $seconds);
    }

    private function takenAt(mixed $raw): ?string
    {
        if (!is_string($raw)) {
            return null;
        }

        $parsed = DateTimeImmutable::createFromFormat(self::EXIF_DATE_FORMAT, trim($raw));

        return $parsed === false ? null : $parsed->format(self::META_DATE_FORMAT);
    }

    private function rational(mixed $raw): ?float
    {
        if (is_int($raw) || is_float($raw)) {
            return (float) $raw;
        }
        if (!is_string($raw)) {
            return null;
        }

        $trimmed = trim($raw);
        if (!str_contains($trimmed, '/')) {
            return is_numeric($trimmed) ? (float) $trimmed : null;
        }

        [$numerator, $denominator] = explode('/', $trimmed, 2);
        if (!is_numeric($numerator) || !is_numeric($denominator) || (float) $denominator === 0.0) {
            return null;
        }

        return (float) $numerator / (float) $denominator;
    }
}
