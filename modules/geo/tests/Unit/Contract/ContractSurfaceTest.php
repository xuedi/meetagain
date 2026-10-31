<?php declare(strict_types=1);

namespace Module\Geo\Tests\Unit\Contract;

use Module\Geo\Contract\DirectoryInterface;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionUnionType;

final class ContractSurfaceTest extends TestCase
{
    private const string CONTRACT_DIR = __DIR__ . '/../../../src/Contract';

    public function testEveryDirectoryMethodSpeaksInScalarsAndViews(): void
    {
        // Arrange
        $reflection = new ReflectionClass(DirectoryInterface::class);
        $offenders = [];

        // Act
        foreach ($reflection->getMethods() as $method) {
            $types = [$method->getReturnType()];
            foreach ($method->getParameters() as $parameter) {
                $types[] = $parameter->getType();
            }
            foreach ($types as $type) {
                $named = $type instanceof ReflectionUnionType ? $type->getTypes() : [$type];
                foreach ($named as $single) {
                    if ($single instanceof ReflectionNamedType && !$single->isBuiltin() && !str_starts_with($single->getName(), 'Module\\Geo\\Contract\\')) {
                        $offenders[] = $method->getName() . ': ' . $single->getName();
                    }
                }
            }
        }

        // Assert
        self::assertSame([], $offenders);
    }

    public function testTheContractNamespaceHoldsNothingButInterfacesEnumsAndReadonlyValueObjects(): void
    {
        // Arrange
        $offenders = [];

        // Act
        foreach (glob(self::CONTRACT_DIR . '/*.php') ?: [] as $file) {
            $class = 'Module\\Geo\\Contract\\' . basename($file, '.php');
            $reflection = new ReflectionClass($class);
            if ($reflection->isInterface() || $reflection->isEnum()) {
                continue;
            }
            if (!$reflection->isFinal() || !$reflection->isReadOnly()) {
                $offenders[] = $class;
            }
        }

        // Assert
        self::assertSame([], $offenders);
    }
}
