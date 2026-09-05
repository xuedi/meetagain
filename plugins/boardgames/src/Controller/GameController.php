<?php declare(strict_types=1);

namespace Plugin\Boardgames\Controller;

use App\Activity\ActivityService;
use App\Controller\AbstractController;
use App\Item\ListRegistry;
use App\Item\Tag\AssignmentFormHelper;
use App\Item\Tag\TagService;
use App\Service\Seo\BreadcrumbBuilder;
use Plugin\Boardgames\Activity\Messages\GameAdded;
use Plugin\Boardgames\Form\GameEditType;
use Plugin\Boardgames\Form\GameLookupType;
use Plugin\Boardgames\Form\GameManualType;
use Plugin\Boardgames\Service\GameLookupResolver;
use Plugin\Boardgames\Service\GameService;
use Plugin\Boardgames\Service\ShelfService;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/boardgames')]
final class GameController extends AbstractController
{
    public function __construct(
        private readonly GameService $gameService,
        private readonly GameLookupResolver $lookupResolver,
        private readonly ShelfService $shelfService,
        private readonly ActivityService $activityService,
        private readonly AssignmentFormHelper $assignmentFormHelper,
        private readonly TagService $tagService,
    ) {}

    #[Route('', name: 'app_boardgames_gamelist', methods: ['GET'])]
    public function list(): Response
    {
        return $this->render('@Boardgames/game/list.html.twig');
    }

    #[Route('/lookup', name: 'app_plugin_boardgames_game_lookup', methods: ['GET'])]
    #[IsGranted('ROLE_STEWARD')]
    public function lookup(Request $request): Response
    {
        $adapter = $this->lookupResolver->resolve();
        if ($adapter === null) {
            $this->addFlash('info', 'boardgames_game.flash_no_adapter');

            return $this->redirectToRoute('app_plugin_boardgames_game_manual');
        }

        $form = $this->createForm(GameLookupType::class, null, ['method' => 'GET']);
        $form->handleRequest($request);

        $results = [];
        if ($form->isSubmitted() && $form->isValid()) {
            $results = $adapter->searchByName((string) $form->get('query')->getData());
            $failure = $adapter->getLastFailure();
            if ($failure !== null) {
                $this->addFlash('error', $failure->flashKey());
            }
        }

        return $this->render('@Boardgames/game/lookup.html.twig', [
            'form' => $form,
            'results' => $results,
            'adapterSource' => $adapter->getSource(),
        ]);
    }

    #[Route('/import', name: 'app_plugin_boardgames_game_import', methods: ['POST'])]
    #[IsGranted('ROLE_STEWARD')]
    public function import(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('app_plugin_boardgames_game_import', (string) $request->request->get('_token'))) {
            throw new BadRequestHttpException('Invalid CSRF token.');
        }

        $externalId = (string) $request->request->get('externalId', '');
        if ($externalId === '') {
            throw $this->createNotFoundException();
        }

        $adapter = $this->lookupResolver->resolve();
        if ($adapter === null) {
            $this->addFlash('error', 'boardgames_game.flash_no_adapter');

            return $this->redirectToRoute('app_plugin_boardgames_game_manual');
        }

        $metadata = $adapter->fetchById($externalId);
        if ($metadata === null) {
            $this->addFlash('error', $adapter->getLastFailure()?->flashKey() ?? 'boardgames_game.flash_not_found');

            return $this->redirectToRoute('app_plugin_boardgames_game_lookup');
        }

        $user = $this->getAuthedUser();

        try {
            $game = $this->gameService->createFromMetadata($metadata, $user->getId());

            $this->activityService->log(GameAdded::TYPE, $user, [
                'game_id' => $game->getId(),
                'game_name' => $game->getName(),
            ]);
            $this->addFlash('success', 'boardgames_game.flash_added');

            return $this->redirectToRoute('app_plugin_boardgames_game_show', ['id' => $game->getId()]);
        } catch (RuntimeException $exception) {
            $this->addFlash('error', $exception->getMessage());

            return $this->redirectToRoute('app_plugin_boardgames_game_lookup');
        }
    }

    #[Route('/manual', name: 'app_plugin_boardgames_game_manual', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_STEWARD')]
    public function manual(Request $request): Response
    {
        $form = $this->createForm(GameManualType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user = $this->getAuthedUser();

            $game = $this->gameService->createManual(
                name: $form->get('name')->getData(),
                yearPublished: $form->get('yearPublished')->getData(),
                minPlayers: $form->get('minPlayers')->getData(),
                maxPlayers: $form->get('maxPlayers')->getData(),
                minPlaytime: $form->get('minPlaytime')->getData(),
                maxPlaytime: $form->get('maxPlaytime')->getData(),
                weight: null,
                description: $form->get('description')->getData(),
                userId: $user->getId(),
            );

            $assignment = $this->assignmentFormHelper->extractAssignment($form);
            $this->tagService->setTags(GameService::ITEM_TYPE, (int) $game->getId(), $assignment);

            $this->activityService->log(GameAdded::TYPE, $user, [
                'game_id' => $game->getId(),
                'game_name' => $game->getName(),
            ]);
            $this->addFlash('success', 'boardgames_game.flash_added');

            return $this->redirectToRoute('app_boardgames_gamelist');
        }

        return $this->render('@Boardgames/game/manual.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_plugin_boardgames_game_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id, ListRegistry $listRegistry, BreadcrumbBuilder $breadcrumbBuilder): Response
    {
        if (!$listRegistry->has(GameService::ITEM_TYPE)) {
            throw $this->createNotFoundException();
        }

        $game = $this->gameService->get($id);
        if ($game === null) {
            throw $this->createNotFoundException('Board game not found');
        }

        $user = $this->getUser();

        return $this->render('@Boardgames/game/detail.html.twig', [
            'game' => $game,
            'owners' => $this->shelfService->getPublicOwners($game),
            'ownership' => $user === null ? null : $this->shelfService->getOwnership($this->getAuthedUser(), $game),
            'breadcrumbs' => $breadcrumbBuilder->build('app_boardgames_gamelist', 'boardgames.menu_main', (string) $game->getName()),
        ]);
    }

    #[Route('/{id}/edit', name: 'app_plugin_boardgames_game_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_STEWARD')]
    public function edit(int $id, Request $request): Response
    {
        $game = $this->gameService->getManaged($id);
        if ($game === null) {
            throw $this->createNotFoundException('Board game not found');
        }

        $form = $this->createForm(GameEditType::class, $game, [
            'attr' => ['enctype' => 'multipart/form-data'],
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user = $this->getAuthedUser();

            try {
                $this->gameService->update(game: $game, boxFile: $form->get('boxFile')->getData(), userId: $user->getId());

                $assignment = $this->assignmentFormHelper->extractAssignment($form);
                $this->tagService->setTags(GameService::ITEM_TYPE, (int) $game->getId(), $assignment);

                $this->addFlash('success', 'boardgames_game.flash_updated');

                return $this->redirectToRoute('app_plugin_boardgames_game_show', ['id' => $game->getId()]);
            } catch (RuntimeException $exception) {
                $this->addFlash('error', $exception->getMessage());
            }
        }

        return $this->render('@Boardgames/game/edit.html.twig', [
            'form' => $form,
            'game' => $game,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_plugin_boardgames_game_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_STEWARD')]
    public function delete(int $id, Request $request): Response
    {
        $game = $this->gameService->getManaged($id);
        if ($game === null) {
            throw $this->createNotFoundException('Board game not found');
        }

        if (!$this->isCsrfTokenValid('app_plugin_boardgames_game_delete' . $id, (string) $request->request->get('_token'))) {
            throw new BadRequestHttpException('Invalid CSRF token.');
        }

        $this->gameService->delete($game);
        $this->addFlash('success', 'boardgames_game.flash_deleted');

        return $this->redirectToRoute('app_boardgames_gamelist');
    }
}
