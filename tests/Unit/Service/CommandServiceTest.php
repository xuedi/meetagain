<?php declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Service\Command\EchoCommand;
use App\Service\CommandService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * does not test anything to be honest ^_^ maybe create a system test to test correct wrapping of command execution
 */
class CommandServiceTest extends TestCase
{
    private MockObject|KernelInterface $kernelMock;
    private MockObject|ParameterBagInterface $parameterMock;
    private CommandService $subject;

    protected function setUp(): void
    {
        $eventDispatcherMock = $this->createMock(EventDispatcher::class);

        $containerMock = $this->createMock(ContainerInterface::class);
        $containerMock
            ->method('get')
            ->with('event_dispatcher')
            ->willReturn($eventDispatcherMock);

        $this->kernelMock = $this->createMock(KernelInterface::class);
        $this->kernelMock
            ->method('getContainer')
            ->willReturn($containerMock);

        $this->parameterMock = $this->createMock(ParameterBagInterface::class);

        $this->subject = new CommandService(
            kernel: $this->kernelMock,
            appParams: $this->parameterMock,
        );
    }

    public function testExecuteCommand(): void
    {
        $this->assertNotEmpty(
            $this->subject->execute(new EchoCommand('test'))
        );
    }

    public function testClearCache(): void
    {
        $this->subject->clearCache();
    }

    public function testExecuteMigrations(): void
    {
        $this->subject->executeMigrations();
    }

    public function testExtractTranslations(): void
    {
        $this->parameterMock
            ->method('get')
            ->with('kernel.enabled_locales')
            ->willReturn(['de', 'en']);

        $this->subject->extractTranslations();
    }
}
