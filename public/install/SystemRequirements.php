<?php declare(strict_types=1);

/**
 * System Requirements Checker
 *
 * Validates that the system meets MeetAgain's requirements:
 * - PHP version
 * - Required extensions
 * - Optional extensions
 * - Directory permissions
 */
class SystemRequirements
{
    /**
     * Check system requirements for MeetAgain installation
     *
     * @return array<string, array{name: string, passed: bool, current: string, optional?: bool}>
     */
    public function check(): array
    {
        $requirements = [];

        // PHP version
        $requirements['php_version'] = [
            'name' => 'PHP >= 8.4',
            'passed' => version_compare(PHP_VERSION, '8.4.0', '>='),
            'current' => PHP_VERSION,
        ];

        // Required extensions
        $extensions = ['pdo', 'pdo_mysql', 'intl', 'iconv', 'ctype', 'json', 'mbstring'];
        foreach ($extensions as $ext) {
            $requirements['ext_' . $ext] = [
                'name' => 'Extension: ' . $ext,
                'passed' => extension_loaded($ext),
                'current' => extension_loaded($ext) ? 'Loaded' : 'Missing',
            ];
        }

        // Optional extensions
        $optionalExtensions = ['apcu', 'imagick', 'gd', 'opcache'];
        foreach ($optionalExtensions as $ext) {
            $requirements['ext_' . $ext . '_optional'] = [
                'name' => 'Extension: ' . $ext . ' (optional)',
                'passed' => true, // optional, always "passes"
                'current' => extension_loaded($ext) ? 'Loaded' : 'Not loaded',
                'optional' => true,
            ];
        }

        // Writable directories
        $writableDirs = ['../../var', '../../var/cache', '../../var/log'];
        foreach ($writableDirs as $dir) {
            $fullPath = __DIR__ . '/' . $dir;
            $exists = is_dir($fullPath);
            $writable = $exists && is_writable($fullPath);
            $requirements['writable_' . basename($dir)] = [
                'name' => 'Writable: ' . $dir,
                'passed' => $writable || !$exists, // Pass if doesn't exist yet
                'current' => $exists ? ($writable ? 'Writable' : 'Not writable') : 'Will be created',
            ];
        }

        return $requirements;
    }

    /**
     * Check if all non-optional requirements pass
     *
     * @param array<string, array{name: string, passed: bool, current: string, optional?: bool}> $requirements
     * @return bool
     */
    public function allRequirementsPassed(array $requirements): bool
    {
        foreach ($requirements as $req) {
            if (!($req['optional'] ?? false) && !$req['passed']) {
                return false;
            }
        }
        return true;
    }
}
