<?php declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class CheckGetRoutesTest extends TestCase
{
    private string $script;
    private string $fixtureDir;

    protected function setUp(): void
    {
        $root = dirname(__DIR__, 2);
        $this->script = $root . '/bin/check-get-routes.php';
        $this->fixtureDir = __DIR__ . '/CheckGetRoutes';
    }

    public function testCleanControllerExitsZero(): void
    {
        // Arrange
        $cleanDir = $this->fixtureDir . '/CleanOnly';

        // Act
        $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($this->script) . ' ' . escapeshellarg($cleanDir);
        $output = [];
        exec($cmd, $output, $exitCode);

        // Assert
        self::assertSame(0, $exitCode, 'Clean controller must exit 0. Output: ' . implode("\n", $output));
    }

    public function testDirtyControllerExitsOne(): void
    {
        // Arrange
        $dirtyDir = $this->fixtureDir . '/DirtyOnly';

        // Act
        $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($this->script) . ' ' . escapeshellarg($dirtyDir);
        $output = [];
        exec($cmd, $output, $exitCode);
        $outputStr = implode("\n", $output);

        // Assert
        self::assertSame(1, $exitCode, 'Dirty controller must exit 1. Output: ' . $outputStr);
        self::assertStringContainsString('->flush(', $outputStr);
        self::assertStringContainsString('DirtyController::index', $outputStr);
    }

    public function testDirtyControllerOutputContainsFilePath(): void
    {
        // Arrange
        $dirtyDir = $this->fixtureDir . '/DirtyOnly';

        // Act
        $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($this->script) . ' ' . escapeshellarg($dirtyDir);
        $output = [];
        exec($cmd, $output, $exitCode);
        $outputStr = implode("\n", $output);

        // Assert
        self::assertMatchesRegularExpression('/DirtyController\.php:\d+/', $outputStr);
    }
}
