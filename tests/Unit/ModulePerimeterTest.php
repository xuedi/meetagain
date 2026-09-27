<?php declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ModulePerimeterTest extends TestCase
{
    private const string CONFIG = 'tests/config/mago.toml';
    private const string SHARED_RULES = 'tests/config/mago-rules.toml';

    #[DataProvider('provideModules')]
    public function testEveryModuleHasItsOwnConfigInCoresRun(string $module, string $namespace): void
    {
        // Arrange
        $config = self::read(self::CONFIG);
        $expected = '"../../modules/' . $module . '/mago.toml"';

        // Act
        $declared = is_file(self::root() . '/' . self::moduleConfig($module)) && str_contains($config, $expected);

        // Assert
        self::assertTrue($declared, sprintf(
            "modules/%s needs a mago.toml, listed in the extends of %s.\nExpected an extends entry: %s\n"
            . 'Copy one from another module; without it core\'s run never sees the module\'s perimeter.',
            $module,
            self::CONFIG,
            $expected,
        ));
    }

    #[DataProvider('provideModules')]
    public function testEveryModuleDeclaresAnInboundRestriction(string $module, string $namespace): void
    {
        // Arrange
        $config = self::read(self::moduleConfig($module));
        $expected = 'dependency = "Module\\\\' . $namespace . '\\\\Internal\\\\**"';

        // Act
        $declared = str_contains($config, $expected);

        // Assert
        self::assertTrue($declared, sprintf(
            "modules/%s has no inbound restriction in %s.\nExpected a line: %s\n"
            . 'Without it the generic backstop still blocks core and plugins, but another module can reach its internals.',
            $module,
            self::moduleConfig($module),
            $expected,
        ));
    }

    #[DataProvider('provideModules')]
    public function testEveryModuleDeclaresAnOutboundRule(string $module, string $namespace): void
    {
        // Arrange
        $config = self::read(self::moduleConfig($module));
        $expected = 'namespace = "Module\\\\' . $namespace . '\\\\"';

        // Act
        $declared = str_contains($config, $expected);

        // Assert
        self::assertTrue($declared, sprintf(
            "modules/%s has no outbound rule in %s.\nExpected a line: %s\n"
            . 'Perimeter rules are an allowlist, so the module would fail the guard on every dependency it has.',
            $module,
            self::moduleConfig($module),
            $expected,
        ));
    }

    #[DataProvider('provideModulesWithTests')]
    public function testAModuleTestSuiteDeclaresItsOwnRule(string $module, string $namespace): void
    {
        // Arrange
        $config = self::read(self::moduleConfig($module));
        $expected = 'namespace = "Module\\\\' . $namespace . '\\\\Tests\\\\"';

        // Act
        $declared = str_contains($config, $expected);

        // Assert
        self::assertTrue($declared, sprintf(
            "modules/%s/tests has no outbound rule in %s.\nExpected a line: %s",
            $module,
            self::moduleConfig($module),
            $expected,
        ));
    }

    #[DataProvider('provideModulesWithTests')]
    public function testAModuleTestSuitePermitsNoCatchAll(string $module, string $namespace): void
    {
        // Arrange
        $rule = self::testRuleOf(self::read(self::moduleConfig($module)), $namespace);

        // Act
        $catchAlls = array_values(array_filter(['"**"', '"App\\\\**"', '"Tests\\\\**"'], static fn(string $permit): bool => str_contains($rule, $permit)));

        // Assert
        self::assertSame(
            [],
            $catchAlls,
            sprintf(
                "The Tests\\ rule in %s permits a catch-all.\nA module's tests reach what its code may, plus PHPUnit; list any other core class with its reason.",
                self::moduleConfig($module),
            ),
        );
    }

    public function testTheGenericBackstopIsInPlace(): void
    {
        // Arrange
        $config = self::read(self::SHARED_RULES);

        // Act
        $declared = str_contains($config, 'dependency = "Module\\\\*\\\\Internal\\\\**"');

        // Assert
        self::assertTrue($declared, 'The generic module backstop restriction is missing from ' . self::SHARED_RULES . '.');
    }

    public function testTheContractShapeIsEnforcedForEveryModule(): void
    {
        // Arrange
        $config = self::read(self::CONFIG);

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

    private static function testRuleOf(string $config, string $namespace): string
    {
        $start = strpos($config, 'namespace = "Module\\\\' . $namespace . '\\\\Tests\\\\"');
        if ($start === false) {
            return '';
        }
        $end = strpos($config, "\n]", $start);

        return substr($config, $start, $end === false ? null : $end - $start);
    }

    private static function moduleConfig(string $module): string
    {
        return 'modules/' . $module . '/mago.toml';
    }

    private static function read(string $relativePath): string
    {
        $path = self::root() . '/' . $relativePath;
        if (!is_file($path)) {
            return '';
        }
        $contents = file_get_contents($path);

        return $contents === false ? '' : $contents;
    }

    private static function root(): string
    {
        return dirname(__DIR__, 2);
    }
}
