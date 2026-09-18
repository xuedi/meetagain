<?php declare(strict_types=1);

namespace App\Service\Security;

use App\Enum\SecurityMeasure;
use App\Service\Config\ConfigService;

readonly class MeasureSettings
{
    public function __construct(
        private ConfigService $configService,
    ) {}

    public function isEnabled(SecurityMeasure $measure): bool
    {
        return $this->configService->getBoolean($measure->configKey(), $measure->defaultEnabled());
    }

    public function setEnabled(SecurityMeasure $measure, bool $enabled): void
    {
        $this->configService->setBoolean($measure->configKey(), $enabled);
    }

    public function proofOfWorkDifficulty(): int
    {
        return min(24, max(8, $this->configService->getInt('security_pow_difficulty', 18)));
    }

    public function logRetentionDays(): int
    {
        return min(365, max(1, $this->configService->getInt('security_measure_log_retention_days', 30)));
    }

    /**
     * @param array<string, mixed> $formData
     */
    public function save(array $formData): void
    {
        $this->configService->setInt('security_pow_difficulty', (int) ($formData['powDifficulty'] ?? 18));
        $this->configService->setInt('security_measure_log_retention_days', (int) ($formData['logRetentionDays'] ?? 30));
    }
}
