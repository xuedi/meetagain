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
     * @return array<string, array{name: string, passed: bool, current: string, tag_class: string, optional?: bool}>
     */
    public function check(): array
    {
        $requirements = [];

        // PHP version
        $phpPassed = version_compare(PHP_VERSION, '8.4.0', '>=');
        $requirements['php_version'] = [
            'name' => 'PHP >= 8.4',
            'passed' => $phpPassed,
            'current' => PHP_VERSION,
            'tag_class' => $phpPassed ? 'is-success' : 'is-danger',
        ];

        // Required extensions
        $extensions = ['pdo', 'pdo_mysql', 'intl', 'iconv', 'ctype', 'json', 'mbstring'];
        foreach ($extensions as $ext) {
            $isLoaded = extension_loaded($ext);
            $requirements['ext_' . $ext] = [
                'name' => 'Extension: ' . $ext,
                'passed' => $isLoaded,
                'current' => $isLoaded ? 'Loaded' : 'Missing',
                'tag_class' => $isLoaded ? 'is-success' : 'is-danger',
            ];
        }

        // Optional extensions
        $optionalExtensions = ['apcu', 'imagick', 'gd', 'opcache'];
        foreach ($optionalExtensions as $ext) {
            $isLoaded = extension_loaded($ext);
            $requirements['ext_' . $ext . '_optional'] = [
                'name' => 'Extension: ' . $ext . ' (optional)',
                'passed' => $isLoaded,
                'current' => $isLoaded ? 'Loaded' : 'Not loaded',
                'optional' => true,
                'tag_class' => $isLoaded ? 'is-success' : 'is-warning',
            ];
        }

        // Writable directories
        $writableDirs = ['../../var', '../../var/cache', '../../var/log'];
        foreach ($writableDirs as $dir) {
            $fullPath = __DIR__ . '/' . $dir;
            $exists = is_dir($fullPath);
            $writable = $exists && is_writable($fullPath);
            $passed = $writable || !$exists;
            $requirements['writable_' . basename($dir)] = [
                'name' => 'Writable: ' . $dir,
                'passed' => $passed,
                'current' => $exists ? ($writable ? 'Writable' : 'Not writable') : 'Will be created',
                'tag_class' => $passed ? 'is-success' : 'is-danger',
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
