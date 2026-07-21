<?php declare(strict_types=1);

namespace Plugin\Glossary\Controller;

use Plugin\Glossary\Entity\Glossary;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/glossary')]
final class IndexController extends AbstractGlossaryController
{
    #[Route('', name: 'app_plugin_glossary', methods: ['GET'])]
    public function show(): Response
    {
        return $this->renderPage('@Glossary/index.html.twig', [
            'itemIds' => $this->listedItemIds(),
        ]);
    }

    #[Route('/{id}', name: 'app_plugin_glossary_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function detail(int $id): Response
    {
        $entry = $this->service->get($id);
        if ($entry === null || (!$entry->getApproved() && !$this->isGranted('ROLE_ORGANIZER'))) {
            throw $this->createNotFoundException();
        }

        return $this->renderPage('@Glossary/detail.html.twig', [
            'entry' => $entry,
        ]);
    }

    /**
     * Moderators see every entry, the ones needing attention first; everyone else only approved ones.
     *
     * @return list<int>
     */
    private function listedItemIds(): array
    {
        $entries = $this->service->getList();

        if (!$this->isGranted('ROLE_ORGANIZER')) {
            $approved = array_filter($entries, static fn(Glossary $entry): bool => $entry->getApproved());

            return array_values(array_map(static fn(Glossary $entry): int => (int) $entry->getId(), $approved));
        }

        $needsAttention = [];
        $rest = [];
        foreach ($entries as $entry) {
            if (!$entry->getApproved() || $entry->getSuggestions() !== []) {
                $needsAttention[] = (int) $entry->getId();
                continue;
            }

            $rest[] = (int) $entry->getId();
        }

        return [...$needsAttention, ...$rest];
    }
}
