<?php declare(strict_types=1);

namespace App\Service;

use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Throwable;

readonly class HealthCheckService
{
    public function __construct(
        private TagAwareCacheInterface $appCache,
        private string $kernelProjectDir,
    ) {
    }

    public function runAll(): array
    {
        return [
            'cache' => $this->testCache(),
            'logSize' => $this->testLogSize(),
            'diskSpace' => $this->testDiskSpace(),
            'phpVersion' => $this->getPhpInfo(),
        ];
    }

    private function testCache(): array
    {
        try {
            $expected = sprintf('test_%d', random_int(0, 100));
            $cacheKey = 'app_admin_health_test';
            $this->appCache->delete($cacheKey);
            $stored = $this->appCache->get($cacheKey, fn () => $expected);
            $this->appCache->delete($cacheKey);

            return ['ok' => $expected === $stored];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    private function testLogSize(): array
    {
        $logFile = $this->kernelProjectDir . '/var/log/dev.log';
        $maxSize = 50 * 1024 * 1024; // 50MB

        if (!file_exists($logFile)) {
            return ['ok' => true, 'size' => 0, 'maxSize' => $maxSize];
        }

        $size = filesize($logFile);

        return [
            'ok' => $size < $maxSize,
            'size' => $size,
            'maxSize' => $maxSize,
        ];
    }

    private function testDiskSpace(): array
    {
        $path = $this->kernelProjectDir;
        $free = disk_free_space($path);
        $total = disk_total_space($path);

        if ($free === false || $total === false) {
            return ['ok' => false, 'error' => 'Could not determine disk space'];
        }

        $percentFree = ($free / $total) * 100;

        return [
            'ok' => $percentFree > 10,
            'free' => $free,
            'total' => $total,
            'percentFree' => round($percentFree, 1),
        ];
    }

    private function getPhpInfo(): array
    {
        return [
            'ok' => true,
            'version' => PHP_VERSION,
            'memoryLimit' => ini_get('memory_limit'),
            'maxExecution' => ini_get('max_execution_time'),
        ];
    }
}
