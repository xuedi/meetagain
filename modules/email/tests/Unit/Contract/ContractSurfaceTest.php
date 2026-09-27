<?php declare(strict_types=1);

namespace Module\Email\Tests\Unit\Contract;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

class ContractSurfaceTest extends TestCase
{
    private const string CONTRACT_DIR = __DIR__ . '/../../../src/Contract';
    private const string NAMESPACE = 'Module\\Email\\Contract\\';

    public function testTheContractNamespaceHoldsNothingButInterfacesEnumsAndReadonlyValueObjects(): void
    {
        // Arrange
        $offenders = [];

        // Act
        foreach ($this->contractClasses() as $class) {
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

    public function testNoContractSignatureNamesADoctrineOrApplicationType(): void
    {
        // Arrange
        $offenders = [];

        // Act
        foreach ($this->contractClasses() as $class) {
            foreach (new ReflectionClass($class)->getMethods() as $method) {
                $offenders = [...$offenders, ...$this->foreignTypesIn($method)];
            }
        }

        // Assert
        self::assertSame([], $offenders);
    }

    public function testNoContractValueObjectCarriesAForeignPropertyType(): void
    {
        // Arrange
        $offenders = [];

        // Act
        foreach ($this->contractClasses() as $class) {
            foreach (new ReflectionClass($class)->getProperties() as $property) {
                if ($this->isOwnType($property->getType())) {
                    continue;
                }

                $offenders[] = $class . '::$' . $property->getName();
            }
        }

        // Assert
        self::assertSame([], $offenders);
    }

    /**
     * @return list<string>
     */
    private function foreignTypesIn(ReflectionMethod $method): array
    {
        $offenders = [];
        foreach ($method->getParameters() as $parameter) {
            if ($this->isOwnType($parameter->getType())) {
                continue;
            }

            $offenders[] = $method->getDeclaringClass()->getName() . '::' . $method->getName() . '($' . $parameter->getName() . ')';
        }
        if (!$this->isOwnType($method->getReturnType())) {
            $offenders[] = $method->getDeclaringClass()->getName() . '::' . $method->getName() . '()';
        }

        return $offenders;
    }

    private function isOwnType(?object $type): bool
    {
        if (!$type instanceof ReflectionNamedType || $type->isBuiltin()) {
            return true;
        }

        $name = $type->getName();
        if (in_array($name, ['self', 'static', 'parent'], true)) {
            return true;
        }

        return str_starts_with($name, self::NAMESPACE) || $name === 'DateTimeImmutable';
    }

    /**
     * @return list<string>
     */
    private function contractClasses(): array
    {
        $classes = [];
        foreach (glob(self::CONTRACT_DIR . '/*.php') ?: [] as $file) {
            $classes[] = self::NAMESPACE . basename($file, '.php');
        }

        return $classes;
    }
}
