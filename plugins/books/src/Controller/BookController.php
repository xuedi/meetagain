<?php declare(strict_types=1);

namespace Plugin\Books\Controller;

use App\Activity\ActivityService;
use App\Controller\AbstractController;
use App\Item\Taxonomy\ItemAssignmentFormHelper;
use App\Item\Taxonomy\ItemTaxonomyService;
use Plugin\Books\Activity\Messages\BookAdded;
use Plugin\Books\Entity\Book;
use Plugin\Books\Form\BookEditType;
use Plugin\Books\Form\BookIsbnType;
use Plugin\Books\Form\BookManualType;
use Plugin\Books\Service\BookService;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/books')]
final class BookController extends AbstractController
{
    public function __construct(
        private readonly BookService $bookService,
        private readonly ActivityService $activityService,
        private readonly ItemAssignmentFormHelper $assignmentFormHelper,
        private readonly ItemTaxonomyService $itemTaxonomyService,
    ) {}

    #[Route('', name: 'app_books_booklist', methods: ['GET'])]
    public function list(): Response
    {
        $itemIds = array_map(static fn(Book $book): int => (int) $book->getId(), $this->bookService->getList());

        return $this->render('@Books/book/list.html.twig', [
            'itemIds' => $itemIds,
        ]);
    }

    #[Route('/add', name: 'app_plugin_books_book_add', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_STEWARD')]
    public function add(Request $request): Response
    {
        $form = $this->createForm(BookIsbnType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user = $this->getAuthedUser();

            try {
                $book = $this->bookService->createFromIsbn($form->get('isbn')->getData(), $user->getId());
                if ($book === null) {
                    $this->addFlash('warning', 'books_book.flash_isbn_not_found');

                    return $this->redirectToRoute('app_plugin_books_book_manual');
                }

                $this->activityService->log(BookAdded::TYPE, $user, [
                    'book_id' => $book->getId(),
                    'book_title' => $book->getTitle(),
                ]);
                $this->addFlash('success', 'books_book.flash_added');

                return $this->redirectToRoute('app_plugin_books_book_show', ['id' => $book->getId()]);
            } catch (RuntimeException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('@Books/book/add.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/manual', name: 'app_plugin_books_book_manual', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_STEWARD')]
    public function manual(Request $request): Response
    {
        $form = $this->createForm(BookManualType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user = $this->getAuthedUser();

            try {
                $book = $this->bookService->createManual(
                    isbn: $form->get('isbn')->getData(),
                    title: $form->get('title')->getData(),
                    author: $form->get('author')->getData(),
                    description: $form->get('description')->getData(),
                    pageCount: $form->get('pageCount')->getData(),
                    publishedYear: $form->get('publishedYear')->getData(),
                    userId: $user->getId(),
                );

                $this->activityService->log(BookAdded::TYPE, $user, [
                    'book_id' => $book->getId(),
                    'book_title' => $book->getTitle(),
                ]);
                $this->addFlash('success', 'books_book.flash_added');

                return $this->redirectToRoute('app_books_booklist');
            } catch (RuntimeException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('@Books/book/manual.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_plugin_books_book_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id): Response
    {
        $book = $this->bookService->get($id);
        if ($book === null) {
            throw $this->createNotFoundException('Book not found');
        }

        return $this->render('@Books/book/detail.html.twig', [
            'book' => $book,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_plugin_books_book_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_STEWARD')]
    public function edit(int $id, Request $request): Response
    {
        $book = $this->bookService->get($id);
        if ($book === null) {
            throw $this->createNotFoundException('Book not found');
        }

        $form = $this->createForm(BookEditType::class, $book, [
            'attr' => ['enctype' => 'multipart/form-data'],
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user = $this->getAuthedUser();
            try {
                $this->bookService->update(book: $book, coverFile: $form->get('coverFile')->getData(), userId: $user->getId());

                $assignment = $this->assignmentFormHelper->extractAssignment($form);
                $this->itemTaxonomyService->setCategory(BookService::ITEM_TYPE, (int) $book->getId(), $assignment['category']);
                $this->itemTaxonomyService->setTags(BookService::ITEM_TYPE, (int) $book->getId(), $assignment['tags']);

                $this->addFlash('success', 'books_book.flash_updated');

                return $this->redirectToRoute('app_plugin_books_book_show', ['id' => $book->getId()]);
            } catch (RuntimeException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('@Books/book/edit.html.twig', [
            'form' => $form,
            'book' => $book,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_plugin_books_book_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_STEWARD')]
    public function delete(int $id, Request $request): Response
    {
        $book = $this->bookService->get($id);
        if ($book === null) {
            throw $this->createNotFoundException('Book not found');
        }

        if (!$this->isCsrfTokenValid('app_plugin_books_book_delete' . $id, (string) $request->request->get('_token'))) {
            throw new BadRequestHttpException('Invalid CSRF token.');
        }

        $this->bookService->delete($book);
        $this->addFlash('success', 'books_book.flash_deleted');

        return $this->redirectToRoute('app_books_booklist');
    }
}
