<?php declare(strict_types=1);

namespace App\Theme;

final readonly class CssRenderer
{
    private const array BULMA_COLORS = [
        'primary' => 'color_primary',
        'link' => 'color_link',
        'info' => 'color_info',
        'success' => 'color_success',
        'warning' => 'color_warning',
        'danger' => 'color_danger',
    ];

    private const array TEXT_TOKENS = [
        'color_text_grey' => '--theme-text-grey',
        'color_text_grey_light' => '--theme-text-grey-light',
    ];

    public function render(Config $colors, Config $baseline): string
    {
        $declarations = [];
        foreach (self::BULMA_COLORS as $name => $key) {
            $hex = $colors->get($key);
            if ($hex === null || $hex === $baseline->get($key)) {
                continue;
            }

            foreach ($this->palette($name, $hex) as $suffix => $value) {
                $declarations[] = sprintf('--bulma-%s-%s: %s;', $name, $suffix, $value);
            }
        }

        foreach (self::TEXT_TOKENS as $key => $property) {
            $hex = $colors->get($key);
            if ($hex !== null && $hex !== $baseline->get($key)) {
                $declarations[] = sprintf('%s: %s;', $property, $hex);
            }
        }

        return implode('', $declarations);
    }

    /**
     * @return array<string, string> declaration suffix => value
     */
    public function palette(string $name, string $hex): array
    {
        [$red, $green, $blue] = array_map(static fn(string $pair): int => (int) hexdec($pair), str_split(substr($hex, 1), 2));
        [$hue, $saturation, $lightness] = $this->rgbToHsl($red / 255, $green / 255, $blue / 255);
        $h = (int) round($hue);
        $s = (int) round($saturation);
        $l = (int) round($lightness);

        $vars = [
            'rgb' => sprintf('%d, %d, %d', $red, $green, $blue),
            'h' => $h . 'deg',
            's' => $s . '%',
            'l' => $l . '%',
        ];

        $lBase = $l % 10;
        [$l0, $l5] = match (true) {
            $lBase < 3 => [$lBase, $lBase + 5],
            $lBase < 8 => [$lBase - 5, $lBase],
            default => [$lBase - 10, $lBase - 5],
        };

        $shades = [];
        $baseDigits = '00';
        for ($i = 0; $i <= 9; $i++) {
            $shades[] = [$i . '0', max($l0 + ($i * 10), 0)];
            $shades[] = [$i . '5', $l5 + ($i * 10)];
            if ($shades[count($shades) - 2][1] === $l) {
                $baseDigits = $i . '0';
            } elseif (($l5 + ($i * 10)) === $l) {
                $baseDigits = $i . '5';
            }
        }
        $shades[] = ['100', min($l0 + 100, 100)];

        foreach ($shades as [$digits, $shade]) {
            $vars[$digits . '-l'] = $shade . '%';
        }

        $combos = [];
        foreach ($shades as [$bgDigits, $bgL]) {
            $bgLum = $this->luminance(...$this->hslToRgb($h, $s, $bgL));
            $bgIsLight = $bgLum > 0.55;
            $found = false;

            if ($bgL === $l) {
                $baseDigits = $bgDigits;
                $combos[$bgDigits] = $bgIsLight ? '10' : '100';
            }

            foreach ($shades as [$fgDigits, $fgL]) {
                $fgLum = $this->luminance(...$this->hslToRgb($h, $s, $fgL));
                $isLightFg = $fgL > $bgL;
                $ratio = $isLightFg ? ($fgLum + 0.05) / ($bgLum + 0.05) : ($bgLum + 0.05) / ($fgLum + 0.05);
                if ($ratio <= 7) {
                    continue;
                }

                if (!$isLightFg) {
                    $combos[$bgDigits] = $fgDigits;
                } elseif (!$found) {
                    $combos[$bgDigits] = $fgDigits;
                    $found = true;
                }
            }

            $combos[$bgDigits] ??= $bgIsLight ? '00' : '100';
        }

        foreach ($shades as [$digits]) {
            $vars[$digits . '-invert-l'] = $this->lightnessVar($name, $combos[$digits]);
        }
        $vars['invert-l'] = $this->lightnessVar($name, $combos[$baseDigits]);
        $vars['light-invert-l'] = $this->lightnessVar($name, $combos['90']);
        $vars['dark-invert-l'] = $this->lightnessVar($name, $combos['10']);
        $vars['on-scheme-l'] = $this->formatNumber($this->onSchemeLightness($red / 255, $green / 255, $blue / 255)) . '%';

        return $vars;
    }

    private function onSchemeLightness(float $red, float $green, float $blue): float
    {
        $schemeLum = $this->luminance(...$this->hslToRgb(221, 14, 100));
        $fgLum = $this->luminance($red, $green, $blue);
        $step = $fgLum > $schemeLum ? 5.0 : -5.0;

        for ($i = 0; $i <= 20; $i++) {
            $ratio = $step > 0 ? ($fgLum + 0.05) / ($schemeLum + 0.05) : ($schemeLum + 0.05) / ($fgLum + 0.05);
            if ($ratio > 5) {
                continue;
            }

            [$hue, $saturation, $lightness] = $this->rgbToHsl($red, $green, $blue);
            [$red, $green, $blue] = $this->hslToRgb($hue, $saturation, max(0.0, min(100.0, $lightness + $step)));
            $fgLum = $this->luminance($red, $green, $blue);
        }

        return $this->rgbToHsl($red, $green, $blue)[2];
    }

    /**
     * @return array{float, float, float} hue in degrees, saturation and lightness in percent
     */
    private function rgbToHsl(float $red, float $green, float $blue): array
    {
        $max = max($red, $green, $blue);
        $min = min($red, $green, $blue);
        $delta = $max - $min;

        $hue = match (true) {
            $max === $min => 0.0,
            $max === $red => fmod((60 * ($green - $blue)) / $delta, 360),
            $max === $green => ((60 * ($blue - $red)) / $delta) + 120,
            default => ((60 * ($red - $green)) / $delta) + 240,
        };
        if ($hue < 0) {
            $hue += 360;
        }

        $lightness = 50 * ($max + $min);
        $saturation = $lightness === 0.0 || $lightness === 100.0 ? 0.0 : (100 * ($max - ($lightness / 100))) / min($lightness / 100, 1 - ($lightness / 100));

        return [$hue, $saturation, $lightness];
    }

    /**
     * @return array{float, float, float} channels from 0 to 1
     */
    private function hslToRgb(float $hue, float $saturation, float $lightness): array
    {
        $scaledHue = fmod($hue / 360, 1);
        $scaledSaturation = $saturation / 100;
        $scaledLightness = $lightness / 100;

        $m2 = $scaledLightness <= 0.5
            ? $scaledLightness * ($scaledSaturation + 1)
            : $scaledLightness + $scaledSaturation - ($scaledLightness * $scaledSaturation);
        $m1 = ($scaledLightness * 2) - $m2;

        return [
            $this->hueToRgb($m1, $m2, $scaledHue + (1 / 3)),
            $this->hueToRgb($m1, $m2, $scaledHue),
            $this->hueToRgb($m1, $m2, $scaledHue - (1 / 3)),
        ];
    }

    private function hueToRgb(float $m1, float $m2, float $hue): float
    {
        if ($hue < 0) {
            $hue += 1;
        }
        if ($hue > 1) {
            $hue -= 1;
        }

        return match (true) {
            $hue < (1 / 6) => $m1 + (($m2 - $m1) * $hue * 6),
            $hue < (1 / 2) => $m2,
            $hue < (2 / 3) => $m1 + (($m2 - $m1) * ((2 / 3) - $hue) * 6),
            default => $m1,
        };
    }

    private function luminance(float $red, float $green, float $blue): float
    {
        $linear = static fn(float $channel): float => $channel < 0.03928 ? $channel / 12.92 : (($channel + 0.055) / 1.055) ** 2;

        return ($linear($red) * 0.2126) + ($linear($green) * 0.7152) + ($linear($blue) * 0.0722);
    }

    private function lightnessVar(string $name, string $digits): string
    {
        return sprintf('var(--bulma-%s-%s-l)', $name, $digits);
    }

    private function formatNumber(float $number): string
    {
        return rtrim(rtrim(number_format($number, 10, '.', ''), '0'), '.');
    }
}
