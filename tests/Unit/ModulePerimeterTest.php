<?php declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ModulePerimeterTest extends TestCase
{
    private const string CONFIG = 'tests/config/mago.toml';
    private const string SHARED_RULES = 'tests/config/mago-rules.toml';

    #[DataProvider('provideModules')]
    public function testEveryModuleHasItsOwnConfigInItsRun(string $module, string $namespace, string $runConfig): void
    {
        // Arrange
        $config = self::read($runConfig);
        $expected = '"../../modules/' . basename($module) . '/mago.toml"';

        // Act
        $declared = is_file(self::root() . '/' . self::moduleConfig($module)) && str_contains($config, $expected);

        // Assert
        self::assertTrue($declared, sprintf(
            "%s needs a mago.toml, listed in the extends of %s.\nExpected an extends entry: %s\n"
            . 'Copy one from another module; without it the run never sees the module\'s perimeter.',
            $module,
            $runConfig,
            $expected,
        ));
    }

    #[DataProvider('provideModules')]
    public function testEveryModuleDeclaresAnInboundRestriction(string $module, string $namespace, string $runConfig): void
    {
        // Arrange
        $config = self::read(self::moduleConfig($module));
        $expected = 'dependency = "' . $namespace . '\\\\Internal\\\\**"';

        // Act
        $declared = str_contains($config, $expected);

        // Assert
        self::assertTrue($declared, sprintf(
            "%s has no inbound restriction in %s.\nExpected a line: %s\n"
            . 'Without it the generic backstop still blocks core and plugins, but another module can reach its internals.',
            $module,
            self::moduleConfig($module),
            $expected,
        ));
    }

    #[DataProvider('provideModules')]
    public function testEveryModuleDeclaresAnOutboundRule(string $module, string $namespace, string $runConfig): void
    {
        // Arrange
        $config = self::read(self::moduleConfig($module));
        $expected = 'namespace = "' . $namespace . '\\\\"';

        // Act
        $declared = str_contains($config, $expected);

        // Assert
        self::assertTrue($declared, sprintf(
            "%s has no outbound rule in %s.\nExpected a line: %s\n"
            . 'Perimeter rules are an allowlist, so the module would fail the guard on every dependency it has.',
            $module,
            self::moduleConfig($module),
            $expected,
        ));
    }

    #[DataProvider('provideModulesWithTests')]
    public function testAModuleTestSuiteDeclaresItsOwnRule(string $module, string $namespace, string $runConfig): void
    {
        // Arrange
        $config = self::read(self::moduleConfig($module));
        $expected = 'namespace = "' . $namespace . '\\\\Tests\\\\"';

        // Act
        $declared = str_contains($config, $expected);

        // Assert
        self::assertTrue($declared, sprintf("%s/tests has no outbound rule in %s.\nExpected a line: %s", $module, self::moduleConfig($module), $expected));
    }

    #[DataProvider('provideModulesWithTests')]
    public function testAModuleTestSuitePermitsNoCatchAll(string $module, string $namespace, string $runConfig): void
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

    #[DataProvider('provideNamespaceRoots')]
    public function testTheGenericBackstopIsInPlace(string $root): void
    {
        // Arrange
        $config = self::read(self::SHARED_RULES);

        // Act
        $declared = str_contains($config, 'dependency = "' . $root . '\\\\Internal\\\\**"');

        // Assert
        self::assertTrue($declared, 'The generic backstop restriction on ' . $root . ' is missing from ' . self::SHARED_RULES . '.');
    }

    #[DataProvider('provideNamespaceRoots')]
    public function testTheContractShapeIsEnforcedForEveryModule(string $root): void
    {
        // Arrange
        $config = self::read(self::SHARED_RULES);

        // Act
        $occurrences = substr_count($config, 'on = "' . $root . '\\\\Contract\\\\**"');

        // Assert
        self::assertGreaterThanOrEqual(2, $occurrences, 'Both structural rules on ' . $root . ' contracts must be present in ' . self::SHARED_RULES . '.');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideNamespaceRoots(): iterable
    {
        yield 'core modules' => ['Module\\\\*'];
        yield 'plugin modules' => ['Plugin\\\\*\\\\Module\\\\*'];
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function provideModules(): iterable
    {
        foreach (self::modules() as $module => [$namespace, $runConfig]) {
            yield $module => [$module, $namespace, $runConfig];
        }
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function provideModulesWithTests(): iterable
    {
        foreach (self::modules() as $module => [$namespace, $runConfig]) {
            if (!is_dir(self::root() . '/' . $module . '/tests')) {
                continue;
            }
            yield $module => [$module, $namespace, $runConfig];
        }
    }

    /**
     * @return array<string, array{string, string}> module directory => [namespace root as written in TOML, the config whose run owns it]
     */
    private static function modules(): array
    {
        $modules = [];
        foreach (glob(self::root() . '/modules/*/src', GLOB_ONLYDIR) ?: [] as $src) {
            $name = basename(dirname($src));
            $modules['modules/' . $name] = ['Module\\\\' . ucfirst($name), self::CONFIG];
        }
        foreach (glob(self::root() . '/plugins/*/modules/*/src', GLOB_ONLYDIR) ?: [] as $src) {
            $name = basename(dirname($src));
            $plugin = basename(dirname($src, 3));
            $namespace = 'Plugin\\\\' . ucfirst($plugin) . '\\\\Module\\\\' . ucfirst($name);
            $modules['plugins/' . $plugin . '/modules/' . $name] = [$namespace, 'plugins/' . $plugin . '/tests/config/mago.toml'];
        }

        return $modules;
    }

    private static function testRuleOf(string $config, string $namespace): string
    {
        $start = strpos($config, 'namespace = "' . $namespace . '\\\\Tests\\\\"');
        if ($start === false) {
            return '';
        }
        $end = strpos($config, "\n]", $start);

        return substr($config, $start, $end === false ? null : $end - $start);
    }

    private static function moduleConfig(string $module): string
    {
        return $module . '/mago.toml';
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
