<?php declare(strict_types=1);

namespace Tests\Unit\Portability\Section;

use App\Circulation\ContextResolver;
use App\Circulation\DefaultContextProvider;
use App\Entity\User;
use App\Portability\DataCategory;
use App\Portability\Outcome;
use App\Portability\Scope;
use App\Portability\Section\TrustSection;
use App\Repository\UserRepository;
use DateTimeImmutable;
use Module\Trust\Contract\GrantTransferInterface;
use Module\Trust\Contract\PortableGrant;
use Module\Trust\Contract\TrustLevel;

final class TrustSectionTest extends SectionTestCase
{
    /** @var list<PortableGrant> */
    private array $restored = [];

    public function testGrantsBetweenConsentingMembersTravelUnderTheirItemType(): void
    {
        // Arrange
        $grants = $this->createMock(GrantTransferInterface::class);
        $grants
            ->expects($this->once())
            ->method('exportGrants')
            ->with(['book-group-7'])
            ->willReturn([
                new PortableGrant(
                    'book-group-7',
                    1,
                    2,
                    TrustLevel::Trusted,
                    new DateTimeImmutable('2026-01-05T10:00:00+01:00'),
                    new DateTimeImmutable('2026-02-01T12:00:00+01:00'),
                ),
                new PortableGrant(
                    'book-group-7',
                    3,
                    1,
                    TrustLevel::Slight,
                    new DateTimeImmutable('2026-01-06T10:00:00+01:00'),
                    new DateTimeImmutable('2026-01-06T10:00:00+01:00'),
                ),
            ]);
        $scope = new Scope(
            users: [1 => 'admin', 2 => 'user', 3 => 'user'],
            grants: [1 => [DataCategory::Interactions], 2 => [DataCategory::Interactions], 3 => [DataCategory::Collections]],
            circulationContexts: ['book-group-7' => 'book'],
        );

        // Act
        $rows = $this->section($grants)->export($scope, $this->images());

        // Assert
        static::assertSame(
            [[
                'item_type' => 'book',
                'from_email' => 'member-1@example.org',
                'to_email' => 'member-2@example.org',
                'level' => 'trusted',
                'created_at' => '2026-01-05T10:00:00+01:00',
                'updated_at' => '2026-02-01T12:00:00+01:00',
            ]],
            $rows,
        );
    }

    public function testAScopeWithoutContextsExportsNothing(): void
    {
        // Arrange
        $grants = $this->createMock(GrantTransferInterface::class);
        $grants->expects($this->never())->method('exportGrants');

        // Act
        $rows = $this->section($grants)->export(new Scope(users: [1 => 'admin']), $this->images());

        // Assert
        static::assertSame([], $rows);
    }

    public function testAnImportedGrantIsFiledUnderThisInstancesContext(): void
    {
        // Arrange
        $context = $this->context();
        $context->mapRef(User::class, 'member-1@example.org', $this->member(11));
        $context->mapRef(User::class, 'member-2@example.org', $this->member(12));

        // Act
        $this->section($this->recordingGrants())->import([[
            'item_type' => 'book',
            'from_email' => 'member-1@example.org',
            'to_email' => 'member-2@example.org',
            'level' => 'absolute',
            'created_at' => '2026-01-05T10:00:00+01:00',
            'updated_at' => '2026-02-01T12:00:00+01:00',
        ]], $context);

        // Assert
        static::assertEquals(
            [new PortableGrant(
                'book',
                11,
                12,
                TrustLevel::Absolute,
                new DateTimeImmutable('2026-01-05T10:00:00+01:00'),
                new DateTimeImmutable('2026-02-01T12:00:00+01:00'),
            )],
            $this->restored,
        );
        static::assertSame(1, $context->toSummary()->get('trust', Outcome::Created));
    }

    public function testAnUnknownMemberASelfEdgeAndAnUnknownLevelAreDropped(): void
    {
        // Arrange
        $context = $this->context();
        $context->mapRef(User::class, 'member-1@example.org', $this->member(11));
        $row = ['item_type' => 'book', 'from_email' => 'member-1@example.org', 'level' => 'trusted'];

        // Act
        $this->section($this->recordingGrants())->import([
            [...$row, 'to_email' => 'stranger@example.org'],
            [...$row, 'to_email' => 'member-1@example.org'],
            [...$row, 'to_email' => 'member-1@example.org', 'level' => 'boundless'],
        ], $context);

        // Assert
        static::assertSame([], $this->restored);
        static::assertSame(3, $context->toSummary()->get('trust', Outcome::Dropped));
    }

    private function section(GrantTransferInterface $grants): TrustSection
    {
        $userRepository = $this->createStub(UserRepository::class);
        $userRepository->method('findBy')->willReturnCallback(fn(array $criteria): array => array_map($this->member(...), $criteria['id']));

        return new TrustSection($grants, $userRepository, new ContextResolver([new DefaultContextProvider()]));
    }

    private function recordingGrants(): GrantTransferInterface
    {
        $grants = $this->createStub(GrantTransferInterface::class);
        $grants
            ->method('restoreGrant')
            ->willReturnCallback(function (PortableGrant $grant): void {
                $this->restored[] = $grant;
            });

        return $grants;
    }

    private function member(int $id): User
    {
        $user = $this->withId(new User(), $id);
        $user->setEmail('member-' . $id . '@example.org');

        return $user;
    }
}
