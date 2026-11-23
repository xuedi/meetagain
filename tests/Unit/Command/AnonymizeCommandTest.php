<?php declare(strict_types=1);

namespace Tests\Unit\Command;

use App\Anonymization\AnonymizerInterface;
use App\Anonymization\Context;
use App\Anonymization\Coverage;
use App\Command\AnonymizeCommand;
use App\Entity\Config;
use App\Entity\User;
use App\Repository\ConfigRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AnonymizeCommandTest extends TestCase
{
    private const string HOST = 'staging.example.org';
    private const string HASH = 'argon-hash-of-1234';

    /** @var list<array{int, string}> */
    private array $runs = [];

    protected function setUp(): void
    {
        $this->runs = [];
    }

    /**
     * @param array<string, mixed> $input
     */
    #[DataProvider('provideRefusals')]
    public function testARefusedRunChangesNothing(array $input, array $problems, ?string $allowedHost, string $message): void
    {
        // Arrange
        $tester = new CommandTester($this->command($problems, $allowedHost));

        // Act
        $exitCode = $tester->execute($input);

        // Assert
        self::assertSame(Command::FAILURE, $exitCode);
        self::assertSame([], $this->runs);
        self::assertStringContainsString($message, preg_replace('/\s+/', ' ', $tester->getDisplay()) ?? '');
    }

    /**
     * @return iterable<string, array{array<string, mixed>, list<string>, ?string, string}>
     */
    public static function provideRefusals(): iterable
    {
        $everything = ['--host' => self::HOST, '--erase-personal-data' => true];
        yield 'the instance not opted in' => [$everything, [], null, 'does not allow anonymization'];
        yield 'the instance opted in with an empty host' => [$everything, [], ' ', 'does not allow anonymization'];
        yield 'opted in for another host' => [$everything, [], 'other.example.org', '--host does not match'];
        yield 'another host' => [['--host' => 'meetagain.org', '--erase-personal-data' => true], [], self::HOST, '--host does not match'];
        yield 'another host named by both' => [['--host' => 'meetagain.org', '--erase-personal-data' => true], [], 'meetagain.org', '--host does not match'];
        yield 'no host' => [['--erase-personal-data' => true], [], self::HOST, '--host does not match'];
        yield 'the confirmation missing' => [['--host' => self::HOST], [], self::HOST, 'Pass --erase-personal-data'];
        yield 'an unclaimed table' => [$everything, ['Table "x" is not claimed by any anonymizer.'], self::HOST, 'Table "x" is not claimed'];
    }

    public function testTheRefusalNamesNeitherTheConfiguredNorTheAllowedHost(): void
    {
        // Arrange
        $tester = new CommandTester($this->command([], 'allowed.example.org'));

        // Act
        $tester->execute(['--host' => 'meetagain.org', '--erase-personal-data' => true]);

        // Assert
        self::assertStringNotContainsString(self::HOST, $tester->getDisplay());
        self::assertStringNotContainsString('allowed.example.org', $tester->getDisplay());
    }

    public function testEveryAnonymizerRunsInOrderWithOnePasswordHashAndTheAdminsAreListed(): void
    {
        // Arrange
        $tester = new CommandTester($this->command([], self::HOST));

        // Act
        $exitCode = $tester->execute(['--host' => self::HOST, '--erase-personal-data' => true]);

        // Assert
        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertSame([[10, self::HASH], [20, self::HASH], [90, self::HASH]], $this->runs);
        self::assertStringContainsString('admin: user3@example.invalid', $tester->getDisplay());
    }

    /**
     * @param list<string> $problems
     */
    private function command(array $problems, ?string $allowedHost): AnonymizeCommand
    {
        $configRepo = $this->createStub(ConfigRepository::class);
        $configRepo
            ->method('findOneBy')
            ->willReturn(
                new Config()
                    ->setName('website_url')
                    ->setValue(self::HOST),
            );
        $userRepo = $this->createStub(UserRepository::class);
        $userRepo->method('findAdminUsers')->willReturn([new User()->setEmail('user3@example.invalid')]);
        $coverage = $this->createStub(Coverage::class);
        $coverage->method('problems')->willReturn($problems);
        $hasher = $this->createStub(UserPasswordHasherInterface::class);
        $hasher->method('hashPassword')->willReturn(self::HASH);

        return new AnonymizeCommand(
            $configRepo,
            $userRepo,
            $this->createStub(EntityManagerInterface::class),
            $coverage,
            $hasher,
            [
                $this->anonymizer(90),
                $this->anonymizer(10),
                $this->anonymizer(20),
            ],
            $allowedHost,
        );
    }

    private function anonymizer(int $order): AnonymizerInterface
    {
        $anonymizer = $this->createStub(AnonymizerInterface::class);
        $anonymizer->method('getOrder')->willReturn($order);
        $anonymizer
            ->method('anonymize')
            ->willReturnCallback(function (Context $context) use ($order): void {
                $this->runs[] = [$order, $context->passwordHash];
            });

        return $anonymizer;
    }
}
