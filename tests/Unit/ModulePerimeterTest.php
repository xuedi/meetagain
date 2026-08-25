<?php declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ModulePerimeterTest extends TestCase
{
    private const string CONFIG = 'tests/config/mago.toml';

    #[DataProvider('provideModules')]
    public function testEveryModuleDeclaresAnInboundRestriction(string $module, string $namespace): void
    {
        // Arrange
        $config = self::readConfig();
        $expected = 'dependency = "Module\\\\' . $namespace . '\\\\Internal\\\\**"';

        // Act
        $declared = str_contains($config, $expected);

        // Assert
        self::assertTrue($declared, sprintf(
            "modules/%s has no inbound restriction in %s.\nExpected a line: %s\n"
            . 'Without it the generic backstop still blocks core and plugins, but another module can reach its internals.',
            $module,
            self::CONFIG,
            $expected,
        ));
    }

    #[DataProvider('provideModules')]
    public function testEveryModuleDeclaresAnOutboundRule(string $module, string $namespace): void
    {
        // Arrange
        $config = self::readConfig();
        $expected = 'namespace = "Module\\\\' . $namespace . '\\\\"';

        // Act
        $declared = str_contains($config, $expected);

        // Assert
        self::assertTrue($declared, sprintf(
            "modules/%s has no outbound rule in %s.\nExpected a line: %s\n"
            . 'Perimeter rules are an allowlist, so the module would fail the guard on every dependency it has.',
            $module,
            self::CONFIG,
            $expected,
        ));
    }

    #[DataProvider('provideModulesWithTests')]
    public function testAModuleTestSuiteDeclaresItsOwnRule(string $module, string $namespace): void
    {
        // Arrange
        $config = self::readConfig();
        $expected = 'namespace = "Module\\\\' . $namespace . '\\\\Tests\\\\"';

        // Act
        $declared = str_contains($config, $expected);

        // Assert
        self::assertTrue($declared, sprintf(
            "modules/%s/tests has no outbound rule in %s.\nExpected a line: %s",
            $module,
            self::CONFIG,
            $expected,
        ));
    }

    public function testTheGenericBackstopIsInPlace(): void
    {
        // Arrange
        $config = self::readConfig();

        // Act
        $declared = str_contains($config, 'dependency = "Module\\\\*\\\\Internal\\\\**"');

        // Assert
        self::assertTrue($declared, 'The generic module backstop restriction is missing from ' . self::CONFIG . '.');
    }

    public function testTheContractShapeIsEnforcedForEveryModule(): void
    {
        // Arrange
        $config = self::readConfig();

        // Act
        $occurrences = substr_count($config, 'on = "Module\\\\*\\\\Contract\\\\**"');

        // Assert
        self::assertGreaterThanOrEqual(2, $occurrences, 'Both structural rules on module contracts must be present in ' . self::CONFIG . '.');
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideModules(): iterable
    {
        foreach (self::moduleDirs() as $module) {
            yield $module => [$module, ucfirst($module)];
        }
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideModulesWithTests(): iterable
    {
        foreach (self::moduleDirs() as $module) {
            if (!is_dir(self::root() . '/modules/' . $module . '/tests')) {
                continue;
            }
            yield $module => [$module, ucfirst($module)];
        }
    }

    /**
     * @return list<string>
     */
    private static function moduleDirs(): array
    {
        $dirs = [];
        foreach (glob(self::root() . '/modules/*/src', GLOB_ONLYDIR) ?: [] as $src) {
            $dirs[] = basename(dirname($src));
        }

        return $dirs;
    }

    private static function readConfig(): string
    {
        $path = self::root() . '/' . self::CONFIG;
        $contents = file_get_contents($path);

        return $contents === false ? '' : $contents;
    }

    private static function root(): string
    {
        return dirname(__DIR__, 2);
    }
}
