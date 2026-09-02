<?php declare(strict_types=1);

namespace Tests\Functional\Twig;

use ReflectionMethod;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Throwable;
use Twig\Environment;
use Twig\Extension\ExtensionInterface;
use Twig\TwigFilter;
use Twig\TwigFunction;

class RuntimeWiringTest extends KernelTestCase
{
    public function testEveryRuntimeBackedFunctionResolves(): void
    {
        // Arrange
        $twig = $this->twig();

        // Act
        $broken = [];
        foreach ($twig->getExtensions() as $extension) {
            foreach ($this->functionsOf($extension) as $function) {
                $failure = $this->resolutionFailure($twig, $function->getCallable());
                if ($failure !== null) {
                    $broken[] = $function->getName() . ': ' . $failure;
                }
            }
        }

        // Assert
        static::assertSame([], $broken, "Twig functions with an unresolvable runtime:\n" . implode("\n", $broken));
    }

    public function testEveryRuntimeBackedFilterResolves(): void
    {
        // Arrange
        $twig = $this->twig();

        // Act
        $broken = [];
        foreach ($twig->getExtensions() as $extension) {
            foreach ($this->filtersOf($extension) as $filter) {
                $failure = $this->resolutionFailure($twig, $filter->getCallable());
                if ($failure !== null) {
                    $broken[] = $filter->getName() . ': ' . $failure;
                }
            }
        }

        // Assert
        static::assertSame([], $broken, "Twig filters with an unresolvable runtime:\n" . implode("\n", $broken));
    }

    private function resolutionFailure(Environment $twig, mixed $callable): ?string
    {
        if (!is_array($callable) || !is_string($callable[0] ?? null) || !is_string($callable[1] ?? null)) {
            return null;
        }

        [$class, $method] = $callable;

        if (!class_exists($class)) {
            return $class . ' does not exist';
        }

        if (!method_exists($class, $method)) {
            return $class . '::' . $method . '() does not exist';
        }

        $reflection = new ReflectionMethod($class, $method);
        $twigInvokesStaticCallablesItself = $reflection->isStatic();
        if ($twigInvokesStaticCallablesItself) {
            return null;
        }

        if (!$reflection->isPublic()) {
            return $class . '::' . $method . '() is not public';
        }

        try {
            $twig->getRuntime($class);
        } catch (Throwable $exception) {
            return $class . ' is not loadable as a Twig runtime (' . $exception->getMessage() . ')';
        }

        return null;
    }

    /** @return list<TwigFunction> */
    private function functionsOf(ExtensionInterface $extension): array
    {
        return array_values($extension->getFunctions());
    }

    /** @return list<TwigFilter> */
    private function filtersOf(ExtensionInterface $extension): array
    {
        return array_values($extension->getFilters());
    }

    private function twig(): Environment
    {
        static::bootKernel();
        $twig = static::getContainer()->get('twig');
        static::assertInstanceOf(Environment::class, $twig);

        return $twig;
    }
}
