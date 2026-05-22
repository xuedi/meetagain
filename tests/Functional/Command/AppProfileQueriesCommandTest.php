<?php declare(strict_types=1);

namespace Tests\Functional\Command;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

class AppProfileQueriesCommandTest extends KernelTestCase
{
    private string $jsonPath;

    protected function setUp(): void
    {
        $this->jsonPath = sys_get_temp_dir() . '/query-profile-test-' . bin2hex(random_bytes(4)) . '.json';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->jsonPath)) {
            unlink($this->jsonPath);
        }
    }

    public function testCommandProducesJsonReportWithExpectedSchema(): void
    {
        // Arrange
        $tester = $this->makeTester();

        // Act
        $tester->execute([
            '--anonymous-only' => true,
            '--limit' => '2',
            '--json' => $this->jsonPath,
        ]);

        // Assert
        $tester->assertCommandIsSuccessful();
        static::assertFileExists($this->jsonPath);

        $payload = json_decode((string) file_get_contents($this->jsonPath), true);
        static::assertIsArray($payload);
        static::assertArrayHasKey('generated_at', $payload);
        static::assertArrayHasKey('route_count', $payload);
        static::assertArrayHasKey('results', $payload);
        static::assertCount($payload['route_count'], $payload['results']);

        foreach ($payload['results'] as $row) {
            static::assertSame('GET', $row['method']);
            static::assertArrayHasKey('url', $row);
            static::assertArrayHasKey('status', $row);
            static::assertArrayHasKey('query_count', $row);
            static::assertArrayHasKey('duration_ms', $row);
            static::assertArrayHasKey('authenticated', $row);
            static::assertArrayHasKey('flag', $row);
        }
    }

    public function testLimitOptionCapsResults(): void
    {
        // Arrange
        $tester = $this->makeTester();

        // Act
        $tester->execute([
            '--anonymous-only' => true,
            '--limit' => '3',
            '--json' => $this->jsonPath,
        ]);

        // Assert
        $tester->assertCommandIsSuccessful();
        $payload = json_decode((string) file_get_contents($this->jsonPath), true);
        static::assertLessThanOrEqual(3, $payload['route_count']);
    }

    public function testAnonymousOnlyProducesUnauthenticatedRowsOnly(): void
    {
        // Arrange
        $tester = $this->makeTester();

        // Act
        $tester->execute([
            '--anonymous-only' => true,
            '--limit' => '2',
            '--json' => $this->jsonPath,
        ]);

        // Assert
        $tester->assertCommandIsSuccessful();
        $payload = json_decode((string) file_get_contents($this->jsonPath), true);
        static::assertNotEmpty($payload['results']);
        foreach ($payload['results'] as $row) {
            static::assertFalse($row['authenticated']);
        }
    }

    public function testResultsAreSortedDescByQueryCount(): void
    {
        // Arrange
        $tester = $this->makeTester();

        // Act
        $tester->execute([
            '--anonymous-only' => true,
            '--limit' => '5',
            '--json' => $this->jsonPath,
        ]);

        // Assert
        $tester->assertCommandIsSuccessful();
        $payload = json_decode((string) file_get_contents($this->jsonPath), true);

        $counts = array_map(static fn(array $r): int => (int) $r['query_count'], $payload['results']);
        $sorted = $counts;
        rsort($sorted);
        static::assertSame($sorted, $counts);
    }

    public function testMutuallyExclusiveOptionsFail(): void
    {
        // Arrange
        $tester = $this->makeTester();

        // Act
        $exitCode = $tester->execute([
            '--anonymous-only' => true,
            '--admin-only' => true,
        ]);

        // Assert
        static::assertNotSame(0, $exitCode);
        static::assertStringContainsString('mutually exclusive', $tester->getDisplay());
    }

    private function makeTester(): CommandTester
    {
        $kernel = self::bootKernel();
        $application = new Application($kernel);
        $command = $application->find('app:profile:queries');

        return new CommandTester($command);
    }
}
