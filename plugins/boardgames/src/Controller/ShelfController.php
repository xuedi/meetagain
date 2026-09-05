<?php declare(strict_types=1);

namespace Plugin\Boardgames\Controller;

use App\Controller\AbstractController;
use Plugin\Boardgames\Entity\GameOwnership;
use Plugin\Boardgames\Enum\CopyCondition;
use Plugin\Boardgames\Service\GameService;
use Plugin\Boardgames\Service\ShelfService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/boardgames/shelf')]
#[IsGranted('ROLE_USER')]
final class ShelfController extends AbstractController
{
    public function __construct(
        private readonly ShelfService $shelfService,
        private readonly GameService $gameService,
    ) {}

    #[Route('', name: 'app_plugin_boardgames_shelf', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('@Boardgames/shelf/index.html.twig', [
            'shelf' => $this->shelfService->getShelf($this->getAuthedUser()),
            'conditions' => CopyCondition::cases(),
        ]);
    }

    #[Route('/add/{id}', name: 'app_plugin_boardgames_shelf_add', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function add(int $id, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('app_plugin_boardgames_shelf_add' . $id, (string) $request->request->get('_token'))) {
            throw new BadRequestHttpException('Invalid CSRF token.');
        }

        $game = $this->gameService->get($id);
        if ($game === null) {
            throw $this->createNotFoundException('Board game not found');
        }

        $this->shelfService->add($this->getAuthedUser(), $game);
        $this->addFlash('success', 'boardgames_shelf.flash_added');

        return $this->redirectToRoute('app_plugin_boardgames_game_show', ['id' => $id]);
    }

    #[Route('/{id}/update', name: 'app_plugin_boardgames_shelf_update', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function update(int $id, Request $request): Response
    {
        $ownership = $this->ownRow($id);

        if (!$this->isCsrfTokenValid('app_plugin_boardgames_shelf_update' . $id, (string) $request->request->get('_token'))) {
            throw new BadRequestHttpException('Invalid CSRF token.');
        }

        $language = trim((string) $request->request->get('copy_language', ''));
        $notes = trim((string) $request->request->get('notes', ''));

        $ownership->setCopyLanguage($language === '' ? null : $language);
        $ownership->setNotes($notes === '' ? null : $notes);
        $ownership->setCopyCondition(CopyCondition::tryFrom((string) $request->request->get('condition', '')));
        $ownership->setCanTeach($request->request->getBoolean('can_teach'));
        $ownership->setWillingToBring($request->request->getBoolean('willing_to_bring'));
        $ownership->setPublic($request->request->getBoolean('is_public'));

        $this->shelfService->save($ownership);
        $this->addFlash('success', 'boardgames_shelf.flash_updated');

        return $this->redirectToRoute('app_plugin_boardgames_shelf');
    }

    #[Route('/{id}/remove', name: 'app_plugin_boardgames_shelf_remove', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function remove(int $id, Request $request): Response
    {
        $ownership = $this->ownRow($id);

        if (!$this->isCsrfTokenValid('app_plugin_boardgames_shelf_remove' . $id, (string) $request->request->get('_token'))) {
            throw new BadRequestHttpException('Invalid CSRF token.');
        }

        $this->shelfService->remove($ownership);
        $this->addFlash('success', 'boardgames_shelf.flash_removed');

        return $this->redirectToRoute('app_plugin_boardgames_shelf');
    }

    private function ownRow(int $id): GameOwnership
    {
        foreach ($this->shelfService->getShelf($this->getAuthedUser()) as $ownership) {
            if ($ownership->getId() === $id) {
                return $ownership;
            }
        }

        throw $this->createNotFoundException('Shelf entry not found');
    }
}
