<?php declare(strict_types=1);

namespace Plugin\Bookclub\Controller;

use App\Controller\AbstractController;
use Plugin\Bookclub\Form\BookIsbnType;
use Plugin\Bookclub\Service\BookService;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/bookclub')]
class BookController extends AbstractController
{
    public function __construct(
        private readonly BookService $bookService,
    ) {}

    #[Route('', name: 'app_plugin_bookclub', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('@Bookclub/index.html.twig', [
            'books' => $this->bookService->getApprovedList(),
        ]);
    }

    #[Route('/book/{id}', name: 'app_plugin_bookclub_book_show', methods: ['GET'])]
    public function show(int $id): Response
    {
        $book = $this->bookService->get($id);
        if ($book === null) {
            throw $this->createNotFoundException('Book not found');
        }

        return $this->render('@Bookclub/book/detail.html.twig', [
            'book' => $book,
        ]);
    }

    #[Route('/book/add', name: 'app_plugin_bookclub_book_add', methods: ['GET', 'POST'], priority: 10)]
    #[IsGranted('ROLE_USER')]
    public function add(Request $request): Response
    {
        $form = $this->createForm(BookIsbnType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $isbn = $form->get('isbn')->getData();
            $user = $this->getAuthedUser();
            $isManager = $this->isGranted('ROLE_MANAGER');

            try {
                $book = $this->bookService->createFromIsbn($isbn, $user->getId(), $isManager);

                if ($book === null) {
                    $this->addFlash('warning', 'Book not found via ISBN lookup. Please try a different ISBN.');
                    return $this->redirectToRoute('app_plugin_bookclub_book_add');
                }

                if ($isManager) {
                    $this->addFlash('success', 'Book added successfully.');
                } else {
                    $this->addFlash('success', 'Book submitted for approval.');
                }

                return $this->redirectToRoute('app_plugin_bookclub_book_show', ['id' => $book->getId()]);
            } catch (RuntimeException $e) {
                $this->addFlash('danger', $e->getMessage());
            }
        }

        return $this->render('@Bookclub/book/add.html.twig', [
            'form' => $form,
        ]);
    }
}
