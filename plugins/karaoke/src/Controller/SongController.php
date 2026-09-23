<?php declare(strict_types=1);

namespace Plugin\Karaoke\Controller;

use App\Activity\ActivityService;
use App\Controller\AbstractController;
use App\Item\Tag\AssignmentFormHelper;
use App\Item\Tag\TagService;
use App\Service\Config\LanguageService;
use App\Service\Member\ConsentService;
use App\Service\Seo\BreadcrumbBuilder;
use Plugin\Karaoke\Activity\Messages\SongAdded;
use Plugin\Karaoke\Entity\LyricLine;
use Plugin\Karaoke\Entity\Song;
use Plugin\Karaoke\Form\SongType;
use Plugin\Karaoke\Service\LyricsText;
use Plugin\Karaoke\Service\SongService;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/karaoke')]
final class SongController extends AbstractController
{
    public function __construct(
        private readonly SongService $songService,
        private readonly ActivityService $activityService,
        private readonly AssignmentFormHelper $assignmentFormHelper,
        private readonly TagService $tagService,
        private readonly LanguageService $languageService,
        private readonly LyricsText $lyricsText,
    ) {}

    #[Route('', name: 'app_plugin_karaoke', methods: ['GET'])]
    public function list(): Response
    {
        return $this->render('@Karaoke/index.html.twig');
    }

    #[Route('/new', name: 'app_plugin_karaoke_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ORGANIZER')]
    public function new(Request $request): Response
    {
        $form = $this->createForm(SongType::class, null, [
            'translation_language' => $this->translationLanguage($request),
            'translation_languages' => $this->languageService->getAdminFilteredEnabledCodes(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user = $this->getAuthedUser();
            $song = $this->songService->create(
                title: $form->get('title')->getData(),
                artist: $form->get('artist')->getData(),
                language: $form->get('language')->getData(),
                link: $form->get('mediaLink')->getData(),
                lyrics: (string) $form->get('lyrics')->getData(),
                translationLanguage: $form->get('translationLanguage')->getData(),
                userId: $user->getId(),
            );
            $this->saveTags($form, $song);

            $this->activityService->log(SongAdded::TYPE, $user, [
                'song_id' => $song->getId(),
                'song_title' => $song->getTitle(),
            ]);
            $this->addFlash('success', 'karaoke.flash_added');

            return $this->redirectToRoute('app_plugin_karaoke_show', ['id' => $song->getId()]);
        }

        return $this->render('@Karaoke/form.html.twig', [
            'form' => $form,
            'song' => null,
        ]);
    }

    #[Route('/{id}', name: 'app_plugin_karaoke_show', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function show(int $id, Request $request, BreadcrumbBuilder $breadcrumbBuilder, ConsentService $consentService): Response
    {
        $song = $this->songService->get($id);
        if ($song === null) {
            throw $this->createNotFoundException();
        }

        return $this->render('@Karaoke/detail.html.twig', [
            'song' => $song,
            'loadMedia' => $consentService->getShowExternalMedia() || $this->isLoadRequested($request),
            'sourceLocale' => $this->languageService->getFilteredDefaultLocale(),
            'breadcrumbs' => $breadcrumbBuilder->build('app_plugin_karaoke', 'karaoke.menu_main', (string) $song->getTitle()),
        ]);
    }

    #[Route('/{id}/edit', name: 'app_plugin_karaoke_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_ORGANIZER')]
    public function edit(int $id, Request $request): Response
    {
        $song = $this->songService->getManaged($id);
        if ($song === null) {
            throw $this->createNotFoundException();
        }

        $translationLanguage = $this->translationLanguage($request);
        $form = $this->createForm(SongType::class, null, [
            'song' => $song,
            'lyrics' => $this->songService->lyricsFor($song, $translationLanguage),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->songService->update(
                song: $song,
                title: $form->get('title')->getData(),
                artist: $form->get('artist')->getData(),
                language: $form->get('language')->getData(),
                link: $form->get('mediaLink')->getData(),
                lyrics: (string) $form->get('lyrics')->getData(),
                translationLanguage: $translationLanguage,
            );
            $this->saveTags($form, $song);
            $this->addFlash('success', 'karaoke.flash_updated');

            return $this->redirectToRoute('app_plugin_karaoke_show', ['id' => $song->getId()]);
        }

        return $this->render('@Karaoke/form.html.twig', [
            'form' => $form,
            'song' => $song,
            'translationLanguage' => $translationLanguage,
            'translationLanguages' => $this->languageService->getAdminFilteredEnabledCodes(),
        ]);
    }

    #[Route('/{id}/timing', name: 'app_plugin_karaoke_timing', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_ORGANIZER')]
    public function timing(int $id, Request $request, ConsentService $consentService): Response
    {
        $song = $this->songService->getManaged($id);
        if ($song === null) {
            throw $this->createNotFoundException();
        }

        $isSave = $request->isMethod('POST') && $request->request->has('offset');
        if ($isSave) {
            if (!$this->isCsrfTokenValid('app_plugin_karaoke_timing' . $id, (string) $request->request->get('_token'))) {
                throw new BadRequestHttpException('Invalid CSRF token.');
            }

            $starts = array_map(fn(mixed $value): ?int => $this->lyricsText->parseTimestamp((string) $value), array_values($request->request->all('start')));
            $this->songService->saveTiming($song, $starts, $request->request->getInt('offset'));
            $this->addFlash('success', 'karaoke.flash_timing_saved');

            return $this->redirectToRoute('app_plugin_karaoke_show', ['id' => $id]);
        }

        return $this->render('@Karaoke/timing.html.twig', [
            'song' => $song,
            'loadMedia' => $consentService->getShowExternalMedia() || $this->isLoadRequested($request),
            'starts' => array_map(fn(?int $ms): string => $ms === null ? '' : $this->lyricsText->formatTimestamp($ms), array_map(
                static fn(LyricLine $line): ?int => $line->getStartMs(),
                $song->getLines()->getValues(),
            )),
        ]);
    }

    #[Route('/{id}/offset', name: 'app_plugin_karaoke_offset', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_ORGANIZER')]
    public function offset(int $id, Request $request): Response
    {
        $song = $this->songService->getManaged($id);
        if ($song === null) {
            throw $this->createNotFoundException();
        }

        if (!$this->isCsrfTokenValid('app_plugin_karaoke_offset' . $id, (string) $request->request->get('_token'))) {
            throw new BadRequestHttpException('Invalid CSRF token.');
        }

        $this->songService->nudgeOffset($song, $request->request->getInt('delta'));

        return $this->redirectToRoute('app_plugin_karaoke_show', ['id' => $id]);
    }

    #[Route('/{id}/delete', name: 'app_plugin_karaoke_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_ORGANIZER')]
    public function delete(int $id, Request $request): Response
    {
        $song = $this->songService->getManaged($id);
        if ($song === null) {
            throw $this->createNotFoundException();
        }

        if (!$this->isCsrfTokenValid('app_plugin_karaoke_delete' . $id, (string) $request->request->get('_token'))) {
            throw new BadRequestHttpException('Invalid CSRF token.');
        }

        $this->songService->delete($song);
        $this->addFlash('success', 'karaoke.flash_deleted');

        return $this->redirectToRoute('app_plugin_karaoke');
    }

    private function isLoadRequested(Request $request): bool
    {
        $isLoadPost = $request->isMethod('POST') && $request->request->get('load') === '1';

        return $isLoadPost && $this->isCsrfTokenValid('external_media_consent', (string) $request->request->get('_token'));
    }

    private function translationLanguage(Request $request): string
    {
        $codes = $this->languageService->getAdminFilteredEnabledCodes();
        $requested = $request->query->getString('lang', $request->getLocale());

        return in_array($requested, $codes, true) ? $requested : $codes[0] ?? $request->getLocale();
    }

    private function saveTags(FormInterface $form, Song $song): void
    {
        $this->tagService->setTags(SongService::ITEM_TYPE, (int) $song->getId(), $this->assignmentFormHelper->extractAssignment($form));
    }
}
