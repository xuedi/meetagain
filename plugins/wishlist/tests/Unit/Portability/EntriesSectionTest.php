<?php declare(strict_types=1);

namespace Plugin\Wishlist\Tests\Unit\Portability;

use App\Entity\User;
use App\Portability\DataCategory;
use App\Portability\Outcome;
use App\Portability\Scope;
use App\Repository\UserRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Plugin\Wishlist\Entity\WishlistEntry;
use Plugin\Wishlist\Portability\EntriesSection;
use Tests\Unit\Portability\Section\SectionTestCase;

final class EntriesSectionTest extends SectionTestCase
{
    public function testAWishSurvivesTheRoundTrip(): void
    {
        // Arrange
        $exported = $this->section([$this->entry(1, 7)], [$this->user(1, 'ada@example.org')])
            ->export($this->scope([1 => [DataCategory::Collections]]), $this->images());

        $importedAda = $this->user(11, 'ada@example.org');
        $context = $this->context();
        $context->mapItems('film', [7 => 70]);
        $context->mapRef(User::class, 'ada@example.org', $importedAda);

        // Act
        $this->section()->import($exported, $context);

        // Assert
        $entry = $this->onlyPersisted(WishlistEntry::class);
        static::assertSame(11, $entry->getUserId());
        static::assertSame('film', $entry->getItemType());
        static::assertSame(70, $entry->getItemId());
        static::assertSame(3, $entry->getPriorityCounter());
        static::assertSame('2026-01-05 10:00', $entry->getCreatedAt()?->format('Y-m-d H:i'));
        static::assertSame(1, $context->toSummary()->get('wishlist', Outcome::Created));
    }

    public function testAWishOfAMemberWithoutTheCollectionsGrantStaysBehind(): void
    {
        // Arrange
        $section = $this->section([$this->entry(1, 7)], [$this->user(1, 'ada@example.org')]);

        // Act
        $exported = $section->export($this->scope([1 => [DataCategory::Interactions]]), $this->images());

        // Assert
        static::assertSame([], $exported);
    }

    public function testAWishTheMemberAlreadyHasIsMatched(): void
    {
        // Arrange
        $context = $this->context();
        $context->mapItems('film', [7 => 70]);
        $context->mapRef(User::class, 'ada@example.org', $this->user(11, 'ada@example.org'));

        // Act
        $this->section(existing: new WishlistEntry())->import([['email' => 'ada@example.org', 'item_type' => 'film', 'item_ref' => 7]], $context);

        // Assert
        static::assertSame(1, $context->toSummary()->get('wishlist', Outcome::Matched));
        static::assertSame([], $this->persisted);
    }

    public function testAnUnknownTypeIsSkippedAndAMissingItemOrMemberDropped(): void
    {
        // Arrange
        $context = $this->context();
        $context->mapItems('film', [7 => 70]);
        $context->mapRef(User::class, 'ada@example.org', $this->user(11, 'ada@example.org'));
        $rows = [
            ['email' => 'ada@example.org', 'item_type' => 'book', 'item_ref' => 3],
            ['email' => 'ada@example.org', 'item_type' => 'film', 'item_ref' => 8],
            ['email' => 'gone@example.org', 'item_type' => 'film', 'item_ref' => 7],
        ];

        // Act
        $this->section()->import($rows, $context);

        // Assert
        $summary = $context->toSummary();
        static::assertSame(1, $summary->get('wishlist', Outcome::Skipped));
        static::assertSame(2, $summary->get('wishlist', Outcome::Dropped));
        static::assertSame([], $this->persisted);
    }

    /**
     * @param list<WishlistEntry> $entries
     * @param list<User> $users
     */
    private function section(array $entries = [], array $users = [], ?WishlistEntry $existing = null): EntriesSection
    {
        $repository = $this->createStub(EntityRepository::class);
        $repository->method('findBy')->willReturn($entries);
        $repository->method('findOneBy')->willReturn($existing);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repository);
        $em->method('persist')->willReturnCallback(function (object $entity): void {
            $this->persisted[] = $entity;
        });

        $userRepository = $this->createStub(UserRepository::class);
        $userRepository->method('findBy')->willReturn($users);

        return new EntriesSection($em, $userRepository);
    }

    /**
     * @param array<int, list<DataCategory>> $grants
     */
    private function scope(array $grants): Scope
    {
        return new Scope(users: [1 => 'user'], itemIds: ['film' => [7]], grants: $grants);
    }

    private function entry(int $userId, int $itemId): WishlistEntry
    {
        return new WishlistEntry()
            ->setUserId($userId)
            ->setItemType('film')
            ->setItemId($itemId)
            ->setPriorityCounter(3)
            ->setCreatedAt(new DateTimeImmutable('2026-01-05 10:00'));
    }

    private function user(int $id, string $email): User
    {
        $user = $this->withId(new User(), $id);
        $user->setEmail($email);

        return $user;
    }
}
