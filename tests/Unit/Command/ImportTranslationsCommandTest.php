<?php declare(strict_types=1);

namespace Tests\Unit\Command;

use App\Command\ImportTranslationsCommand;
use App\Service\TranslationImportService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class ImportTranslationsCommandTest extends TestCase
{
    public function testCommandHasCorrectName(): void
    {
        $serviceStub = $this->createStub(TranslationImportService::class);
        $command = new ImportTranslationsCommand($serviceStub);

        $this->assertSame('app:translation:import', $command->getName());
    }

    public function testCommandHasCorrectDescription(): void
    {
        $serviceStub = $this->createStub(TranslationImportService::class);
        $command = new ImportTranslationsCommand($serviceStub);

        $this->assertSame('imports online translations for local development', $command->getDescription());
    }

    public function testCommandRequiresUrlArgument(): void
    {
        $serviceStub = $this->createStub(TranslationImportService::class);
        $command = new ImportTranslationsCommand($serviceStub);

        $definition = $command->getDefinition();
        $this->assertTrue($definition->hasArgument('url'));
        $this->assertTrue($definition->getArgument('url')->isRequired());
    }

    public function testExecuteCallsImportServiceWithUrl(): void
    {
        $testUrl = 'https://example.com/api/translations';

        $serviceMock = $this->createMock(TranslationImportService::class);
        $serviceMock
            ->expects($this->once())
            ->method('importForLocalDevelopment')
            ->with($testUrl);

        $command = new ImportTranslationsCommand($serviceMock);
        $commandTester = new CommandTester($command);

        $exitCode = $commandTester->execute(['url' => $testUrl]);

        $this->assertSame(Command::SUCCESS, $exitCode);
    }
}
