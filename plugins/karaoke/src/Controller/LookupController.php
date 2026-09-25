<?php declare(strict_types=1);

namespace Plugin\Karaoke\Controller;

use App\Controller\AbstractController;
use Plugin\Karaoke\Enum\LookupFailure;
use Plugin\Karaoke\Service\SongLookup;
use Plugin\Karaoke\Service\SongService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/karaoke'), IsGranted('ROLE_ORGANIZER')]
final class LookupController extends AbstractController
{
    public function __construct(
        private readonly SongLookup $songLookup,
        private readonly SongService $songService,
    ) {}

    #[Route('/find', name: 'app_plugin_karaoke_find', methods: ['GET'])]
    public function find(Request $request): Response
    {
        if (!$this->songLookup->isEnabled()) {
            return $this->redirectToRoute('app_plugin_karaoke_new', ['manual' => 1]);
        }

        $pastedUrl = trim($request->query->getString('link'));
        $link = $pastedUrl === '' ? null : $this->songLookup->parseLink($pastedUrl);
        if ($link === null) {
            return $this->render('@Karaoke/new_link.html.twig', [
                'link' => $pastedUrl,
                'invalid' => $pastedUrl !== '',
            ]);
        }

        $lookup = $this->songLookup->candidatesForLink($link, $pastedUrl, $this->searchTitle($request), $request->query->getString('artist'));
        $this->flashFailure($lookup['failure']);

        return $this->render('@Karaoke/candidates.html.twig', [
            'lookup' => $lookup,
            'song' => null,
            'link' => $pastedUrl,
        ]);
    }

    #[Route('/{id}/lookup', name: 'app_plugin_karaoke_lookup', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function lookup(int $id, Request $request): Response
    {
        $song = $this->songService->getManaged($id);
        if ($song === null) {
            throw $this->createNotFoundException();
        }

        $lookup = $this->songLookup->candidatesForSong($song, $this->searchTitle($request), $request->query->getString('artist'));
        $this->flashFailure($lookup['failure']);

        return $this->render('@Karaoke/candidates.html.twig', [
            'lookup' => $lookup,
            'song' => $song,
            'link' => null,
        ]);
    }

    #[Route('/{id}/lookup/timings', name: 'app_plugin_karaoke_lookup_timings', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function timings(int $id, Request $request): Response
    {
        $song = $this->songService->getManaged($id);
        if ($song === null) {
            throw $this->createNotFoundException();
        }

        if (!$this->isCsrfTokenValid('app_plugin_karaoke_lookup_timings' . $id, (string) $request->request->get('_token'))) {
            throw new BadRequestHttpException('Invalid CSRF token.');
        }

        $starts = $this->songLookup->timingsForSong($song, $request->request->getString('pick'));
        if ($starts === null) {
            $this->addFlash('warning', 'karaoke_lookup.flash_timings_mismatch');

            return $this->redirectToRoute('app_plugin_karaoke_lookup', ['id' => $id]);
        }

        $this->songService->saveTiming($song, $starts, 0);
        $this->addFlash('success', 'karaoke_lookup.flash_timings_applied');

        return $this->redirectToRoute('app_plugin_karaoke_show', ['id' => $id]);
    }

    private function searchTitle(Request $request): ?string
    {
        $title = trim($request->query->getString('title'));

        return $title === '' ? null : $title;
    }

    private function flashFailure(?LookupFailure $failure): void
    {
        if ($failure === null) {
            return;
        }

        $this->addFlash('warning', $failure->flashKey());
    }
}
