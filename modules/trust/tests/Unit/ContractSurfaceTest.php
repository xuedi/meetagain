<?php declare(strict_types=1);

namespace Module\Trust\Tests\Unit;

use Module\Trust\Contract\TrustInterface;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

class ContractSurfaceTest extends TestCase
{
    private const string CONTRACT_DIR = __DIR__ . '/../../src/Contract';

    public function testNoContractMethodReturnsAnEdgeForSomebodyElse(): void
    {
        // Arrange
        $reflection = new ReflectionClass(TrustInterface::class);

        // Act
        $edgeReaders = array_filter(
            $reflection->getMethods(),
            static fn(ReflectionMethod $method): bool => str_contains(strtolower($method->getName()), 'incoming')
                || str_contains(strtolower($method->getName()), 'vouchers')
                || str_contains(strtolower($method->getName()), 'grants'),
        );

        // Assert
        self::assertSame([], $edgeReaders);
    }

    public function testTheOutgoingReadIsSubjectScoped(): void
    {
        // Arrange
        $method = new ReflectionMethod(TrustInterface::class, 'getOutgoing');

        // Act
        $names = array_map(static fn($parameter): string => $parameter->getName(), $method->getParameters());

        // Assert
        self::assertSame(['context', 'fromUserId'], $names);
    }

    public function testEveryContractMethodSpeaksInScalarsEnumsAndValueObjects(): void
    {
        // Arrange
        $reflection = new ReflectionClass(TrustInterface::class);
        $offenders = [];

        // Act
        foreach ($reflection->getMethods() as $method) {
            foreach ($method->getParameters() as $parameter) {
                $type = $parameter->getType();
                if ($type instanceof ReflectionNamedType && !$type->isBuiltin() && !str_starts_with($type->getName(), 'Module\\Trust\\Contract\\')) {
                    $offenders[] = $method->getName() . '::$' . $parameter->getName();
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
            $class = 'Module\\Trust\\Contract\\' . basename($file, '.php');
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
