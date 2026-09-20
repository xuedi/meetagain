<?php declare(strict_types=1);

namespace Tests\Unit\Command;

use App\Command\InstallSeedCommand;
use App\Entity\AppState;
use App\Entity\Config;
use App\Entity\Language;
use App\Entity\PronunciationSystem;
use App\Entity\User;
use App\Enum\ConfigType;
use App\Service\Config\LanguageService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class InstallSeedCommandTest extends TestCase
{
    /** @var array<class-string, list<object>> */
    private array $rows = [];

    /** @var list<string> */
    private array $calls = [];

    /** @return iterable<string, array{class-string, int}> */
    public static function seededRowProvider(): iterable
    {
        yield 'the import and cron system users' => [User::class, 2];
        yield 'every language' => [Language::class, 65];
        yield 'every config row including the system user id' => [Config::class, 14];
        yield 'every pronunciation system' => [PronunciationSystem::class, 7];
        yield 'the four footer column titles' => [AppState::class, 4];
    }

    #[DataProvider('seededRowProvider')]
    public function testAnEmptyDatabaseReceivesEveryInstallRow(string $entityClass, int $expected): void
    {
        // Arrange
        $tester = new CommandTester($this->command());

        // Act
        $exitCode = $tester->execute([]);

        // Assert
        static::assertSame(Command::SUCCESS, $exitCode);
        static::assertCount($expected, $this->rows[$entityClass] ?? []);
    }

    public function testASecondRunAddsNothing(): void
    {
        // Arrange
        $tester = new CommandTester($this->command());
        $tester->execute([]);
        $afterFirstRun = array_map(count(...), $this->rows);

        // Act
        $tester->execute([]);

        // Assert
        static::assertSame($afterFirstRun, array_map(count(...), $this->rows));
    }

    public function testAConfigRowAlreadyPresentIsNeitherDuplicatedNorOverwritten(): void
    {
        // Arrange
        $migrated = new Config();
        $migrated->setName('show_town_hall');
        $migrated->setValue('false');
        $migrated->setType(ConfigType::Boolean);
        $this->rows[Config::class] = [$migrated];
        $tester = new CommandTester($this->command());

        // Act
        $tester->execute([]);

        // Assert
        $townHallRows = array_filter(
            $this->rows[Config::class],
            static fn(object $config): bool => $config instanceof Config && $config->getName() === 'show_town_hall',
        );
        static::assertSame([$migrated], array_values($townHallRows));
        static::assertSame('false', $migrated->getValue());
    }

    public function testEmailTemplatesAreSeededAfterTheLanguageCacheIsDropped(): void
    {
        // Arrange
        $tester = new CommandTester($this->command());

        // Act
        $tester->execute([]);

        // Assert
        static::assertSame(['language cache dropped', 'email templates seeded'], $this->calls);
    }

    private function command(): InstallSeedCommand
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getRepository')->willReturnCallback($this->repository(...));
        $em->method('persist')->willReturnCallback(function (object $entity): void {
            $this->rows[$entity::class][] = $entity;
        });

        $hasher = $this->createStub(UserPasswordHasherInterface::class);
        $hasher->method('hashPassword')->willReturn('hash');

        $languageService = $this->createStub(LanguageService::class);
        $languageService
            ->method('invalidateCache')
            ->willReturnCallback(function (): void {
                $this->calls[] = 'language cache dropped';
            });

        $emailTemplateSeed = $this->createStub(Command::class);
        $emailTemplateSeed
            ->method('run')
            ->willReturnCallback(function (): int {
                $this->calls[] = 'email templates seeded';

                return Command::SUCCESS;
            });
        $application = $this->createStub(Application::class);
        $application->method('find')->willReturn($emailTemplateSeed);

        $command = new InstallSeedCommand(
            $em,
            $hasher,
            $languageService,
            $this->createStub(CacheItemPoolInterface::class),
            $this->createStub(CacheItemPoolInterface::class),
        );
        $command->setApplication($application);

        return $command;
    }

    /** @param class-string $entityClass */
    private function repository(string $entityClass): EntityRepository
    {
        $repository = $this->createStub(EntityRepository::class);
        $repository->method('findAll')->willReturnCallback(fn(): array => $this->rows[$entityClass] ?? []);
        $repository
            ->method('findOneBy')
            ->willReturnCallback(fn(array $criteria): ?object => array_find(
                $this->rows[$entityClass] ?? [],
                static fn(object $row): bool => $row instanceof User && $row->getEmail() === $criteria['email'],
            ));

        return $repository;
    }
}
