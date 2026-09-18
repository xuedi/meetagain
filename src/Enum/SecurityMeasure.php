<?php declare(strict_types=1);

namespace App\Enum;

enum SecurityMeasure: string
{
    case ImageCaptcha = 'image_captcha';
    case Honeypot = 'honeypot';
    case SubmitTiming = 'submit_timing';
    case ProofOfWork = 'proof_of_work';

    public function configKey(): string
    {
        return 'security_measure_' . $this->value;
    }

    public function defaultEnabled(): bool
    {
        return $this !== self::ProofOfWork;
    }

    public function labelKey(): string
    {
        return 'admin_system_security.measure_' . $this->value;
    }

    public function descriptionKey(): string
    {
        return 'admin_system_security.measure_' . $this->value . '_help';
    }

    public function icon(): string
    {
        return match ($this) {
            self::ImageCaptcha => 'image',
            self::Honeypot => 'bug',
            self::SubmitTiming => 'stopwatch',
            self::ProofOfWork => 'microchip',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::ImageCaptcha => 'rgb(54, 162, 235)',
            self::Honeypot => 'rgb(255, 159, 64)',
            self::SubmitTiming => 'rgb(75, 192, 192)',
            self::ProofOfWork => 'rgb(153, 102, 255)',
        };
    }

    public function isFormMeasure(): bool
    {
        return match ($this) {
            self::ImageCaptcha, self::Honeypot, self::SubmitTiming, self::ProofOfWork => true,
        };
    }

    /**
     * @return list<string>
     */
    public static function configKeys(): array
    {
        return array_map(static fn(self $measure): string => $measure->configKey(), self::cases());
    }

    /**
     * @return list<self>
     */
    public static function formMeasures(): array
    {
        return array_values(array_filter(self::cases(), static fn(self $measure): bool => $measure->isFormMeasure()));
    }
}
