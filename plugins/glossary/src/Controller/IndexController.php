<?php declare(strict_types=1);

namespace Plugin\Glossary\Controller;

use App\Entity\User;
use App\Item\ListRegistry;
use App\Service\Seo\BreadcrumbBuilder;
use DateTimeImmutable;
use Plugin\Glossary\Item\GlossaryTaggableTypeProvider;
use Plugin\Glossary\Service\ProgressService;
use Plugin\Glossary\Service\TrainerService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/glossary')]
final class IndexController extends AbstractGlossaryController
{
    #[Route('', name: 'app_plugin_glossary', methods: ['GET'])]
    public function show(TrainerService $trainer, ProgressService $progress): Response
    {
        $user = $this->getUser();

        return $this->renderPage('@Glossary/index.html.twig', [
            'trainerSummary' => $trainer->isEnabled() && $user instanceof User ? $progress->summary((int) $user->getId(), new DateTimeImmutable()) : null,
        ]);
    }

    #[Route('/{id}', name: 'app_plugin_glossary_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function detail(
        int $id,
        ListRegistry $listRegistry,
        BreadcrumbBuilder $breadcrumbBuilder,
        TrainerService $trainer,
        ProgressService $progress,
    ): Response {
        if (!$listRegistry->has(GlossaryTaggableTypeProvider::ITEM_TYPE)) {
            throw $this->createNotFoundException();
        }

        $entry = $this->service->get($id);
        if ($entry === null) {
            throw $this->createNotFoundException();
        }

        $user = $this->getUser();
        $trainerEnabled = $trainer->isEnabled();

        return $this->renderPage('@Glossary/detail.html.twig', [
            'entry' => $entry,
            'definition' => $this->service->definitionFor($entry),
            'definitions' => $entry->getDefinitionMap(),
            'breadcrumbs' => $breadcrumbBuilder->build('app_plugin_glossary', 'glossary.menu_main', (string) $entry->getPhrase()),
            'trainerEnabled' => $trainerEnabled,
            'trainerStats' => $trainerEnabled && $user instanceof User ? $progress->entryStats((int) $user->getId(), $id) : null,
            'difficultyBand' => $trainerEnabled ? $progress->difficultyBand($id) : null,
        ]);
    }
}
