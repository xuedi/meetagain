<?php

declare(strict_types=1);

namespace Tests\Unit\Form;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Guards the i18n hardening plan: no validator constraint in src/Form or src/Entity
 * may ship an English literal as its message / minMessage / maxMessage /
 * invalidMessage / mimeTypesMessage. The value must look like a translation key
 * (namespace.key with no spaces).
 */
class ValidatorMessageTranslationKeysTest extends TestCase
{
    private const string FORM_DIR = __DIR__ . '/../../../src/Form';
    private const string ENTITY_DIR = __DIR__ . '/../../../src/Entity';

    private const array MESSAGE_PARAMS = [
        'message',
        'minMessage',
        'maxMessage',
        'invalidMessage',
        'mimeTypesMessage',
        'notFoundMessage',
        'notReadableMessage',
        'maxSizeMessage',
        'exactMessage',
    ];

    #[DataProvider('provideSourceFiles')]
    public function testValidatorMessagesAreTranslationKeys(string $file): void
    {
        // Arrange
        $code = file_get_contents($file);
        static::assertNotFalse($code, "Could not read {$file}");

        // Act - find every `<param>: '...'` pair where <param> is a known message param
        $offenders = [];
        foreach (self::MESSAGE_PARAMS as $param) {
            $pattern = '/\b' . preg_quote($param, '/') . ':\s*([\'"])([^\'"]+)\1/';
            if (preg_match_all($pattern, $code, $matches, PREG_SET_ORDER) === false) {
                continue;
            }
            foreach ($matches as $match) {
                $value = $match[2];
                if (!self::looksLikeTranslationKey($value)) {
                    $offenders[] = "{$param}: '{$value}'";
                }
            }
        }

        // Assert
        static::assertSame(
            [],
            $offenders,
            "Validator messages in {$file} must be translation keys (e.g. 'namespace.key'), "
                . 'not English literals. Offending values: '
                . implode(', ', $offenders),
        );
    }

    public static function provideSourceFiles(): iterable
    {
        foreach (self::collectPhpFiles(self::FORM_DIR) as $file) {
            yield 'Form/' . basename($file) => [$file];
        }
        foreach (self::collectPhpFiles(self::ENTITY_DIR) as $file) {
            yield 'Entity/' . basename($file) => [$file];
        }
    }

    /**
     * @return list<string>
     */
    private static function collectPhpFiles(string $dir): array
    {
        if (!is_dir($dir)) {
            return [];
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
        foreach ($iterator as $file) {
            if (!($file->isFile() && $file->getExtension() === 'php')) {
                continue;
            }

            $files[] = $file->getPathname();
        }
        sort($files);

        return $files;
    }

    private static function looksLikeTranslationKey(string $value): bool
    {
        // A translation key is namespace.key - lowercase letters, digits, dots, underscores.
        // No spaces, no multi-word English text.
        return preg_match('/^[a-z][a-z0-9_]*(\.[a-z0-9_]+)+$/', $value) === 1;
    }
}
