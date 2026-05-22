<?php declare(strict_types=1);

namespace Plugin\Filmclub\Controller;

use App\Activity\ActivityService;
use App\Controller\AbstractController;
use Plugin\Filmclub\Activity\Messages\NoteAdded;
use Plugin\Filmclub\Activity\Messages\NoteRevealed;
use Plugin\Filmclub\Form\NoteType;
use Plugin\Filmclub\Service\FilmService;
use Plugin\Filmclub\Service\NoteService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/filmclub/note')]
#[IsGranted('ROLE_USER')]
final class NoteController extends AbstractController
{
    public function __construct(
        private readonly NoteService $noteService,
        private readonly FilmService $filmService,
        private readonly ActivityService $activityService,
    ) {}

    #[Route('/mine', name: 'app_plugin_filmclub_note_list', methods: ['GET'])]
    public function list(): Response
    {
        $user = $this->getAuthedUser();

        return $this->render('@Filmclub/note/list.html.twig', [
            'notes' => $this->noteService->getForUser($user->getId()),
        ]);
    }

    #[Route('/edit/{filmId}', name: 'app_plugin_filmclub_note_edit', methods: ['GET', 'POST'])]
    public function edit(int $filmId, Request $request): Response
    {
        $film = $this->filmService->get($filmId);
        if ($film === null) {
            throw $this->createNotFoundException('Film not found');
        }

        $user = $this->getAuthedUser();
        $existingNote = $this->noteService->getNoteForUser($user->getId(), $film);

        $data = $existingNote !== null
            ? [
                'body' => $existingNote->getBody(),
                'revealToGroup' => $existingNote->isRevealToGroup(),
            ] : [];

        $form = $this->createForm(NoteType::class, $data);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $wasRevealed = $existingNote?->isRevealToGroup() ?? false;
            $revealToGroup = (bool) $form->get('revealToGroup')->getData();

            $note = $this->noteService->upsert(
                $user->getId(),
                $film,
                (string) $form->get('body')->getData(),
                $revealToGroup,
            );

            $activityType = NoteAdded::TYPE;
            if ($revealToGroup && !$wasRevealed) {
                $activityType = NoteRevealed::TYPE;
            }

            $this->activityService->log($activityType, $user, [
                'film_id' => $film->getId(),
                'film_title' => $film->getTitle(),
            ]);

            $this->addFlash('success', 'filmclub_note.flash_saved');

            return $this->redirectToRoute('app_plugin_filmclub_film_show', ['id' => $filmId]);
        }

        return $this->render('@Filmclub/note/edit.html.twig', [
            'film' => $film,
            'form' => $form,
            'existingNote' => $existingNote,
        ]);
    }
}
