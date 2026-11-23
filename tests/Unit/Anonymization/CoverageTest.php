<?php declare(strict_types=1);

namespace Tests\Unit\Anonymization;

use App\Anonymization\AnonymizerInterface;
use App\Anonymization\Claim;
use App\Anonymization\Context;
use App\Anonymization\Coverage;
use App\Enum\UserRole;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Name\Identifier;
use Doctrine\DBAL\Schema\Name\OptionallyQualifiedName;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\ClassMetadataFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CoverageTest extends TestCase
{
    /**
     * @param list<Claim> $claims
     * @param list<string> $extraTables
     * @param list<string> $expected
     */
    #[DataProvider('provideClaims')]
    public function testProblemsNameWhatIsNotDecided(array $claims, array $extraTables, array $expected): void
    {
        // Arrange
        $subject = new Coverage($this->entityManager($extraTables), [$this->anonymizer($claims)]);

        // Act
        $problems = $subject->problems();

        // Assert
        self::assertSame($expected, $problems);
    }

    /**
     * @return iterable<string, array{list<Claim>, list<string>, list<string>}>
     */
    public static function provideClaims(): iterable
    {
        yield 'every text column decided, the enum column needs no decision' => [
            [Claim::columns('member', rewritten: ['name'], cleared: ['notes'], kept: ['settings'])],
            [],
            [],
        ];
        yield 'an emptied table needs no column decision' => [[Claim::emptied('member')], [], []];
        yield 'an unclaimed table' => [[], [], ['Table "member" (Member) is not claimed by any anonymizer.']];
        yield 'an undecided text column' => [
            [Claim::columns('member', rewritten: ['name'], kept: ['settings'])],
            [],
            ['Column "member.notes" is not decided: rewrite, clear or keep it.'],
        ];
        yield 'a claimed column that does not exist' => [
            [Claim::columns('member', rewritten: ['name', 'nickname'], cleared: ['notes'], kept: ['settings'])],
            [],
            ['Column "member.nickname" is claimed but does not exist.'],
        ];
        yield 'a column decided twice' => [
            [Claim::columns('member', rewritten: ['name', 'notes'], cleared: ['notes'], kept: ['settings'])],
            [],
            ['Column "member.notes" is decided twice.'],
        ];
        yield 'a table claimed twice' => [
            [Claim::emptied('member'), Claim::reference('member')],
            [],
            ['Table "member" is claimed twice.'],
        ];
        yield 'a claimed table that does not exist' => [
            [Claim::emptied('member'), Claim::emptied('gone')],
            [],
            ['Table "gone" is claimed but does not exist.'],
        ];
        yield 'reference data absent from this database' => [[Claim::emptied('member'), Claim::reference('gone')], [], []];
        yield 'a table without an entity that nobody claims' => [
            [Claim::emptied('member')],
            ['failed_messages'],
            ['Table "failed_messages" (no entity) is not claimed by any anonymizer.'],
        ];
        yield 'a table without an entity claimed by its columns' => [
            [Claim::emptied('member'), Claim::columns('failed_messages', kept: ['body'])],
            ['failed_messages'],
            ['Table "failed_messages" has no entity, so it can only be claimed as emptied or as reference data.'],
        ];
        yield 'a join table needs no claim' => [[Claim::emptied('member')], ['member_follows'], []];
    }

    /**
     * @param list<Claim> $claims
     */
    private function anonymizer(array $claims): AnonymizerInterface
    {
        return new readonly class($claims) implements AnonymizerInterface {
            /**
             * @param list<Claim> $claims
             */
            public function __construct(
                private array $claims,
            ) {}

            public function getOrder(): int
            {
                return 0;
            }

            public function getClaims(): array
            {
                return $this->claims;
            }

            public function anonymize(Context $context): void {}
        };
    }

    /**
     * @param list<string> $extraTables
     */
    private function entityManager(array $extraTables): EntityManagerInterface
    {
        $member = new ClassMetadata('Member');
        $member->setPrimaryTable(['name' => 'member']);
        $member->mapField(['fieldName' => 'id', 'type' => 'integer', 'id' => true]);
        $member->mapField(['fieldName' => 'name', 'type' => 'string']);
        $member->mapField(['fieldName' => 'notes', 'type' => 'text']);
        $member->mapField(['fieldName' => 'settings', 'type' => 'json']);
        $member->mapField(['fieldName' => 'role', 'type' => 'string', 'enumType' => UserRole::class]);
        $member->mapManyToMany([
            'fieldName' => 'follows',
            'targetEntity' => 'Member',
            'joinTable' => [
                'name' => 'member_follows',
                'joinColumns' => [['name' => 'member_id', 'referencedColumnName' => 'id']],
                'inverseJoinColumns' => [['name' => 'followed_id', 'referencedColumnName' => 'id']],
            ],
        ]);

        $factory = $this->createStub(ClassMetadataFactory::class);
        $factory->method('getAllMetadata')->willReturn([$member]);
        $schema = $this->createStub(AbstractSchemaManager::class);
        $schema
            ->method('introspectTableNames')
            ->willReturn(array_map(
                static fn(string $table): OptionallyQualifiedName => new OptionallyQualifiedName(Identifier::unquoted($table), null),
                ['member', ...$extraTables],
            ));
        $connection = $this->createStub(Connection::class);
        $connection->method('createSchemaManager')->willReturn($schema);
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getMetadataFactory')->willReturn($factory);
        $em->method('getConnection')->willReturn($connection);

        return $em;
    }
}
