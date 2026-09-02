<?php declare(strict_types=1);

namespace Plugin\Photos\Twig;

use Plugin\Photos\Entity\Photo;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Extension\RuntimeExtensionInterface;

final readonly class ExifRuntime implements RuntimeExtensionInterface
{
    private const array METERING_MODES = [
        1 => 'photos_photo.metering_average',
        2 => 'photos_photo.metering_center_weighted',
        3 => 'photos_photo.metering_spot',
        4 => 'photos_photo.metering_multi_spot',
        5 => 'photos_photo.metering_pattern',
        6 => 'photos_photo.metering_partial',
    ];

    public function __construct(
        private TranslatorInterface $translator,
    ) {}

    /**
     * @return list<array{label: string, value: string}>
     */
    public function rows(?Photo $photo): array
    {
        $meta = $photo?->getMeta();
        if ($photo === null || $meta === null) {
            return [];
        }

        $candidates = [
            'photos_photo.meta_camera' => $this->string($photo->getCameraLabel()),
            'photos_photo.meta_lens' => $this->string($meta['lens'] ?? null),
            'photos_photo.meta_exposure' => $this->exposure($meta['exposureTime'] ?? null),
            'photos_photo.meta_aperture' => $this->aperture($meta['fNumber'] ?? null),
            'photos_photo.meta_iso' => $this->string($meta['iso'] ?? null),
            'photos_photo.meta_focal_length' => $this->millimetres($meta['focalLength'] ?? null),
            'photos_photo.meta_focal_length_35' => $this->millimetres($meta['focalLength35'] ?? null),
            'photos_photo.meta_exposure_bias' => $this->exposureBias($meta['exposureBias'] ?? null),
            'photos_photo.meta_flash' => $this->flash($meta['flash'] ?? null),
            'photos_photo.meta_white_balance' => $this->whiteBalance($meta['whiteBalance'] ?? null),
            'photos_photo.meta_metering' => $this->metering($meta['meteringMode'] ?? null),
            'photos_photo.meta_dimensions' => $this->dimensions($meta),
        ];

        $rows = [];
        foreach ($candidates as $label => $value) {
            if ($value === null) {
                continue;
            }

            $rows[] = ['label' => $label, 'value' => $value];
        }

        return $rows;
    }

    private function string(mixed $value): ?string
    {
        if ($value === null || is_bool($value) || is_array($value)) {
            return null;
        }

        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    private function exposure(mixed $value): ?string
    {
        $text = $this->string($value);

        return $text === null ? null : $text . ' s';
    }

    private function aperture(mixed $value): ?string
    {
        $text = $this->number($value);

        return $text === null ? null : 'f/' . $text;
    }

    private function millimetres(mixed $value): ?string
    {
        $text = $this->number($value);

        return $text === null ? null : $text . ' mm';
    }

    private function exposureBias(mixed $value): ?string
    {
        if (!is_numeric($value)) {
            return null;
        }

        $signed = (float) $value > 0.0 ? '+' . $this->number($value) : $this->number($value);

        return $signed . ' EV';
    }

    private function flash(mixed $value): ?string
    {
        if (!is_bool($value)) {
            return null;
        }

        return $this->translator->trans($value ? 'photos_photo.flash_fired' : 'photos_photo.flash_not_fired');
    }

    private function whiteBalance(mixed $value): ?string
    {
        if (!is_numeric($value)) {
            return null;
        }

        return $this->translator->trans((int) $value === 0 ? 'photos_photo.wb_auto' : 'photos_photo.wb_manual');
    }

    private function metering(mixed $value): ?string
    {
        if (!is_numeric($value)) {
            return null;
        }

        $key = self::METERING_MODES[(int) $value] ?? null;

        return $key === null ? null : $this->translator->trans($key);
    }

    /** @param array<string, scalar> $meta */
    private function dimensions(array $meta): ?string
    {
        $width = $meta['width'] ?? null;
        $height = $meta['height'] ?? null;
        if (!is_numeric($width) || !is_numeric($height)) {
            return null;
        }

        return sprintf('%d × %d', (int) $width, (int) $height);
    }

    private function number(mixed $value): ?string
    {
        if (!is_numeric($value)) {
            return null;
        }

        return rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
    }
}
