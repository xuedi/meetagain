<?php

declare(strict_types=1);

const MIB = 1024 * 1024;

const EXPECTED_LIMITS = [
    'memory' => [Imagick::RESOURCETYPE_MEMORY, 512 * MIB],
    'map' => [Imagick::RESOURCETYPE_MAP, 256 * MIB],
    'disk' => [Imagick::RESOURCETYPE_DISK, 512 * MIB],
    'width' => [Imagick::RESOURCETYPE_WIDTH, 16_000],
    'height' => [Imagick::RESOURCETYPE_HEIGHT, 16_000],
    'area' => [Imagick::RESOURCETYPE_AREA, 128_000_000],
];

const ALLOWED_FORMATS = ['JPEG', 'PNG', 'GIF', 'WEBP', 'AVIF'];

const DENIED_CODERS = ['SVG', 'MSVG', 'MVG', 'MSL', 'TEXT', 'LABEL', 'PS', 'PDF'];

$failures = [];

foreach (EXPECTED_LIMITS as $name => [$type, $expected]) {
    $actual = Imagick::getResourceLimit($type);
    if ($actual != $expected) {
        $failures[] = sprintf('resource limit "%s" is %s, expected %s', $name, $actual, $expected);
    }
}

if (Imagick::getResourceLimit(Imagick::RESOURCETYPE_TIME) < PHP_INT_MAX) {
    $failures[] = 'a "time" limit is set - ImageMagick 6 counts it per process and kills the long-lived server';
}

foreach (ALLOWED_FORMATS as $format) {
    try {
        $image = new Imagick();
        $image->newImage(4, 4, new ImagickPixel('red'));
        $image->setImageFormat($format);
        (new Imagick())->readImageBlob($image->getImageBlob());
    } catch (ImagickException $e) {
        $failures[] = sprintf('%s cannot be written and read back: %s', $format, $e->getMessage());
    }
}

$probe = tempnam(sys_get_temp_dir(), 'policy-probe');
file_put_contents($probe, '<svg xmlns="http://www.w3.org/2000/svg" width="1" height="1"></svg>');
foreach (DENIED_CODERS as $coder) {
    try {
        (new Imagick())->readImage($coder . ':' . $probe);
        $failures[] = sprintf('%s is readable - the coder allow-list is not in force', $coder);
    } catch (ImagickException $e) {
        if (!str_contains($e->getMessage(), sprintf("security policy `%s'", $coder))) {
            $failures[] = sprintf('%s failed for a reason other than the policy: %s', $coder, $e->getMessage());
        }
    }
}
unlink($probe);

if ($failures !== []) {
    fwrite(STDERR, "ImageMagick policy check failed:\n  - " . implode("\n  - ", $failures) . "\n");
    exit(1);
}

echo "ImageMagick policy is in force\n";
