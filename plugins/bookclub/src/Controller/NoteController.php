<?php declare(strict_types=1);

namespace Plugin\Bookclub\Controller;

use App\Controller\AbstractController;
use Plugin\Bookclub\Form\NoteType;
use Plugin\Bookclub\Service\BookService;
use Plugin\Bookclub\Service\NoteService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/bookclub')]
#[IsGranted('ROLE_USER')]
class NoteController extends AbstractController
{
    public function __construct(
        private readonly NoteService $noteService,
        private readonly BookService $bookService,
    ) {}

    #[Route('/notes', name: 'app_plugin_bookclub_notes', methods: ['GET'])]
    public function list(): Response
    {
        $user = $this->getAuthedUser();

        return $this->render('@Bookclub/note/list.html.twig', [
            'notes' => $this->noteService->getUserNotes($user->getId()),
        ]);
    }

    #[Route('/note/{bookId}', name: 'app_plugin_bookclub_note_edit', methods: ['GET', 'POST'])]
    public function edit(int $bookId, Request $request): Response
    {
        $book = $this->bookService->get($bookId);
        if ($book === null) {
            throw $this->createNotFoundException('Book not found');
        }

        $user = $this->getAuthedUser();
        $existingNote = $this->noteService->getNote($book, $user->getId());

        $form = $this->createForm(NoteType::class, [
            'content' => $existingNote?->getContent(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $content = $form->get('content')->getData();
            $this->noteService->saveNote($book, $user->getId(), $content);
            $this->addFlash('success', 'Note saved.');

            return $this->redirectToRoute('app_plugin_bookclub_notes');
        }

        return $this->render('@Bookclub/note/edit.html.twig', [
            'book' => $book,
            'form' => $form,
            'existingNote' => $existingNote,
        ]);
    }
}
