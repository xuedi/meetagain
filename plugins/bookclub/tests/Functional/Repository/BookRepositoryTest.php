<?php declare(strict_types=1);

namespace Plugin\Bookclub\Tests\Functional\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Plugin\Bookclub\Entity\Book;
use Plugin\Bookclub\Repository\BookRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class BookRepositoryTest extends KernelTestCase
{
    public function testFindApprovedEagerLoadsRelationsToAvoidNPlus1(): void
    {
        // Arrange
        self::bootKernel();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        /** @var BookRepository $repo */
        $repo = $em->getRepository(Book::class);

        // Act
        $books = $repo->findApproved();

        // Assert
        static::assertNotEmpty($books, 'Test fixtures must contain at least one approved book.');

        foreach ($books as $book) {
            static::assertTrue(
                $book->getSelections()->isInitialized(),
                sprintf('Book %d selections collection is lazy - findApproved() should eager-load it.', (int) $book->getId()),
            );

            foreach ($book->getSelections() as $selection) {
                $event = $selection->getEvent();
                static::assertFalse(
                    $em->getUnitOfWork()->isUninitializedObject($event),
                    sprintf('Event %d on book %d is an uninitialized proxy - the selection.event join should hydrate it eagerly.', $event->getId(), (int) $book->getId()),
                );

                static::assertTrue(
                    $event->getTranslations()->isInitialized(),
                    sprintf('Event %d translations collection is lazy - the event.translations join should eager-load it.', $event->getId()),
                );
            }
        }
    }
}
