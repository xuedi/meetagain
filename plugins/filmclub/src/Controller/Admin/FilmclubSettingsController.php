<?php declare(strict_types=1);

namespace Plugin\Filmclub\Controller\Admin;

use App\Controller\AbstractController;
use Plugin\Filmclub\Filter\FilmGroupFilterService;
use Plugin\Filmclub\Form\FilmclubSettingsType;
use Plugin\Filmclub\Service\FilmclubSettingsService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/filmclub/settings')]
#[IsGranted('ROLE_STEWARD')]
final class FilmclubSettingsController extends AbstractController
{
    public function __construct(
        private readonly FilmGroupFilterService $filterService,
        private readonly FilmclubSettingsService $settingsService,
    ) {}

    #[Route('', name: 'app_plugin_filmclub_settings_chooser', methods: ['GET'])]
    public function chooser(): Response
    {
        $allowedIds = $this->filterService->getAllowedSettingsIds();

        if ($allowedIds === []) {
            throw $this->createNotFoundException();
        }

        if ($allowedIds !== null && count($allowedIds) === 1) {
            return $this->redirectToRoute('app_plugin_filmclub_settings_edit', [
                'groupId' => $allowedIds[0],
            ]);
        }

        if ($allowedIds !== null) {
            return $this->render('@Filmclub/settings/chooser.html.twig', [
                'groupIds' => $allowedIds,
            ]);
        }

        // No filter active (single-tenant): redirect to edit for group 1
        return $this->redirectToRoute('app_plugin_filmclub_settings_edit', ['groupId' => 1]);
    }

    #[Route('/{groupId}', name: 'app_plugin_filmclub_settings_edit', methods: ['GET', 'POST'])]
    public function edit(int $groupId, Request $request): Response
    {
        $allowedIds = $this->filterService->getAllowedSettingsIds();

        if ($allowedIds !== null && !in_array($groupId, $allowedIds, true)) {
            throw $this->createNotFoundException();
        }

        $settings = $this->settingsService->getOrCreate($groupId);

        $form = $this->createForm(FilmclubSettingsType::class, $settings, [
            'tmdb_key_set' => $settings->getEncryptedTmdbKey() !== null,
            'omdb_key_set' => $settings->getEncryptedOmdbKey() !== null,
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $tmdbKey = $form->get('tmdbKey')->getData();
            $clearTmdb = (bool) $form->get('clearTmdbKey')->getData();
            $omdbKey = $form->get('omdbKey')->getData();
            $clearOmdb = (bool) $form->get('clearOmdbKey')->getData();

            if ($clearTmdb) {
                $settings->setEncryptedTmdbKey(null);
            } elseif (!empty($tmdbKey)) {
                $settings->setEncryptedTmdbKey($this->settingsService->encryptKey($tmdbKey));
            }

            if ($clearOmdb) {
                $settings->setEncryptedOmdbKey(null);
            } elseif (!empty($omdbKey)) {
                $settings->setEncryptedOmdbKey($this->settingsService->encryptKey($omdbKey));
            }

            $this->settingsService->save($settings);
            $this->addFlash('success', 'filmclub_settings.flash_saved');

            return $this->redirectToRoute('app_plugin_filmclub_settings_edit', [
                'groupId' => $groupId,
            ]);
        }

        return $this->render('@Filmclub/settings/edit.html.twig', [
            'form' => $form,
            'groupId' => $groupId,
            'settings' => $settings,
        ]);
    }
}
