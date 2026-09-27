<?php declare(strict_types=1);

namespace Tests\Unit;

use FilesystemIterator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class ModuleTableTest extends TestCase
{
    private const string PREFIX = 'mod_';

    /**
     * @param list<string> $tables
     */
    #[DataProvider('provideModuleEntities')]
    public function testAModuleEntityOwnsOnlyTablesUnderItsModulePrefix(string $file, string $module, array $tables): void
    {
        // Arrange
        $own = self::PREFIX . $module;

        // Act
        $foreign = array_values(array_filter($tables, static fn(string $table): bool => $table !== $own && !str_starts_with($table, $own . '_')));

        // Assert
        self::assertSame(
            [],
            $foreign,
            sprintf(
                "%s is an entity of the module '%s', so every table it maps is '%s' or starts with '%s_'.\nDeclare it with #[ORM\\Table(name: '%s_...')].",
                $file,
                $module,
                $own,
                $own,
                $own,
            ),
        );
    }

    /**
     * @param list<string> $tables
     */
    #[DataProvider('provideOtherEntities')]
    public function testAnEntityOutsideAModuleNeverUsesTheModulePrefix(string $file, array $tables): void
    {
        // Act
        $claimed = array_values(array_filter($tables, static fn(string $table): bool => str_starts_with($table, self::PREFIX)));

        // Assert
        self::assertSame([], $claimed, sprintf("%s is not a module entity, so no table it maps may start with '%s'.", $file, self::PREFIX));
    }

    public function testEveryModuleNameIsOneLowercaseWordUsedOnce(): void
    {
        // Arrange
        $names = array_values(self::modules());

        // Act
        $malformed = array_values(array_filter($names, static fn(string $name): bool => preg_match('~^[a-z][a-z0-9]*$~', $name) !== 1));
        $duplicates = array_values(array_unique(array_diff_assoc($names, array_unique($names))));

        // Assert
        self::assertSame([], $malformed, 'A module name becomes its table prefix mod_<name>_, so it is one lowercase word: no dash, no underscore.');
        self::assertSame(
            [],
            $duplicates,
            'Core modules and plugin modules share the mod_<name>_ table prefix, so a module name is used once across both trees.',
        );
    }

    /**
     * @return iterable<string, array{string, string, list<string>}>
     */
    public static function provideModuleEntities(): iterable
    {
        foreach (self::modules() as $directory => $module) {
            foreach (self::entityFiles($directory . '/src') as $file) {
                yield $file => [$file, $module, self::tablesOf($file)];
            }
        }
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function provideOtherEntities(): iterable
    {
        $directories = [
            'src',
            ...array_map(static fn(string $src): string => self::relative(dirname($src)) . '/src', glob(self::root() . '/plugins/*/src', GLOB_ONLYDIR) ?: []),
        ];
        foreach ($directories as $directory) {
            foreach (self::entityFiles($directory) as $file) {
                yield $file => [$file, self::tablesOf($file)];
            }
        }
    }

    /**
     * @return array<string, string> module directory => module name
     */
    private static function modules(): array
    {
        $modules = [];
        foreach ([
            ...(glob(self::root() . '/modules/*/src', GLOB_ONLYDIR) ?: []),
            ...(glob(self::root() . '/plugins/*/modules/*/src', GLOB_ONLYDIR) ?: []),
        ] as $src) {
            $modules[self::relative(dirname($src))] = basename(dirname($src));
        }

        return $modules;
    }

    /**
     * @return list<string>
     */
    private static function entityFiles(string $directory): array
    {
        $root = self::root() . '/' . $directory;
        if (!is_dir($root)) {
            return [];
        }

        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $entry) {
            $path = $entry->getPathname();
            if (!str_ends_with($path, '.php') || !str_contains((string) file_get_contents($path), '#[ORM\\Entity')) {
                continue;
            }
            $files[] = self::relative($path);
        }
        sort($files);

        return $files;
    }

    /**
     * @return list<string>
     */
    private static function tablesOf(string $file): array
    {
        $source = (string) file_get_contents(self::root() . '/' . $file);
        preg_match_all('~#\[(?:ORM\\\\)?(?:Table|JoinTable)\(\s*name:\s*[\'"]([^\'"]+)[\'"]~', $source, $declared);
        $tables = $declared[1];

        $hasTable = preg_match('~#\[(?:ORM\\\\)?Table\(\s*name:~', $source) === 1;
        if (!$hasTable) {
            $tables[] = self::defaultTableName(basename($file, '.php'));
        }

        return $tables;
    }

    private static function defaultTableName(string $class): string
    {
        return strtolower((string) preg_replace('~(?<=[a-z0-9])([A-Z])~', '_$1', $class));
    }

    private static function relative(string $path): string
    {
        return substr($path, strlen(self::root()) + 1);
    }

    private static function root(): string
    {
        return dirname(__DIR__, 2);
    }
}
