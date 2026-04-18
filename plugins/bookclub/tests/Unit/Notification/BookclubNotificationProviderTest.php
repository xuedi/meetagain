<?php declare(strict_types=1);

namespace Tests\Plugin\Bookclub\Unit\Notification;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\Notification\User\ReviewNotificationItem;
use PHPUnit\Framework\TestCase;
use Plugin\Bookclub\Entity\Book;
use Plugin\Bookclub\Notification\BookclubNotificationProvider;
use Plugin\Bookclub\Repository\BookRepository;
use Plugin\Bookclub\Service\BookService;
use Plugin\Bookclub\Service\PollService;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class BookclubNotificationProviderTest extends TestCase
{
    private function makeBook(int $id, string $title = 'Test Book', ?int $createdBy = null): Book
    {
        $book = $this->createMock(Book::class);
        $book->method('getId')->willReturn($id);
        $book->method('getTitle')->willReturn($title);
        $book->method('getCreatedBy')->willReturn($createdBy);

        return $book;
    }

    private function makeProvider(
        array $pendingBooks = [],
        bool $isOrganizer = true,
    ): BookclubNotificationProvider {
        $bookRepo = $this->createMock(BookRepository::class);
        $bookRepo->method('findBy')->willReturn($pendingBooks);

        $security = $this->createStub(Security::class);
        $security->method('isGranted')->willReturn($isOrganizer);

        return new BookclubNotificationProvider(
            bookRepository: $bookRepo,
            bookService: $this->createStub(BookService::class),
            userRepository: $this->createStub(UserRepository::class),
            pollService: $this->createStub(PollService::class),
            security: $security,
        );
    }

    public function testGetReviewItemsReturnsOneItemPerPendingBook(): void
    {
        // Arrange
        $user = $this->createStub(User::class);
        $provider = $this->makeProvider(pendingBooks: [$this->makeBook(1), $this->makeBook(2)]);

        // Act
        $items = $provider->getReviewItems($user);

        // Assert
        static::assertCount(2, $items);
        static::assertInstanceOf(ReviewNotificationItem::class, $items[0]);
        static::assertSame('1', $items[0]->id);
        static::assertSame('2', $items[1]->id);
    }

    public function testGetReviewItemsReturnsEmptyForNonOrganizer(): void
    {
        // Arrange
        $user = $this->createStub(User::class);
        $provider = $this->makeProvider(pendingBooks: [$this->makeBook(1)], isOrganizer: false);

        // Act
        $items = $provider->getReviewItems($user);

        // Assert
        static::assertSame([], $items);
    }

    public function testApproveItemThrowsForNonOrganizer(): void
    {
        // Arrange
        $user = $this->createStub(User::class);
        $provider = $this->makeProvider(isOrganizer: false);

        // Assert
        $this->expectException(AccessDeniedException::class);

        // Act
        $provider->approveItem($user, '1');
    }

    public function testDenyItemThrowsForNonOrganizer(): void
    {
        // Arrange
        $user = $this->createStub(User::class);
        $provider = $this->makeProvider(isOrganizer: false);

        // Assert
        $this->expectException(AccessDeniedException::class);

        // Act
        $provider->denyItem($user, '1');
    }
}
